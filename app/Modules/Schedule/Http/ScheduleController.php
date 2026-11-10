<?php

namespace App\Modules\Schedule\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\DefaultSlot;
use App\Modules\Schedule\Models\LeaveRequest;
use App\Modules\Schedule\Placement;
use App\Modules\Schedule\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Lịch tuần (chỉ xem, lọc theo người) và Phân công (kéo tên nhân viên vào ô
 * hàng khu/việc × ca × ngày, mỗi lần thả lưu ngay; ô thiếu người so với lịch mặc định thì nhắc).
 */
class ScheduleController extends Controller
{
    public function home(): RedirectResponse
    {
        return redirect()->route('tenant.admin.schedule.week');
    }

    /** Lịch tuần chỉ xem. Người phân công lọc theo nhân viên; người khác bật "chỉ mình tôi". */
    public function week(Request $request): View
    {
        $viewer = $request->user();
        $days = Roster::week($this->date($request->query('date')));
        $shifts = Roster::shiftsByWeekday();
        $users = Roster::users();
        $canFilter = $viewer->can('schedule.manage');
        $seeAll = $viewer->can('schedule.view');

        $people = match (true) {
            ! $seeAll, $request->boolean('mine') => [$viewer->id],
            $canFilter => array_values(array_intersect(array_map('intval', (array) $request->query('people', [])), $users->keys()->all())),
            default => [],
        };

        $assignments = Roster::assignments($days[0], $days[6]);
        $shown = $people === [] ? $assignments : $assignments->whereIn('user_id', $people);
        $grid = Roster::grid($shown);

        return view('modules.schedule.week', [
            'date' => $days[0],
            'days' => $days,
            'slots' => $this->slotsOf($days, $shifts),
            'grid' => $grid,
            'targets' => Roster::targets(),
            'users' => $users,
            'people' => $people,
            'canFilter' => $canFilter,
            'seeAll' => $seeAll,
            'mine' => $people === [$viewer->id],
            // Đang lọc thì ẩn các hàng cả tuần không có ai trong bộ lọc.
            'usedLanes' => $people === [] ? null : $shown->map(fn (Assignment $row) => Roster::laneKey($row))->unique()->values()->all(),
            'totals' => $people === [] ? [] : Roster::totals($shown, $shifts),
        ]);
    }

    /** Màn hình phân công: lịch bên trái, bảng nhân viên nổi bên phải. */
    public function assign(Request $request): View
    {
        $days = Roster::week($this->date($request->query('date')));
        $shifts = Roster::shiftsByWeekday();
        $users = Roster::users();
        $leaves = Placement::leavesInWeek($days);

        return view('modules.schedule.assign', [
            'date' => $days[0],
            'days' => $days,
            'slots' => $this->slotsOf($days, $shifts),
            'grid' => Roster::grid(Roster::assignments($days[0], $days[6])),
            'targets' => Roster::targets(),
            'users' => $users,
            'stats' => $this->stats($users, $days),
            'leaves' => $leaves,
            'leaveMap' => $this->leaveMap($leaves),
            'needed' => Roster::neededByWeekday(),
        ]);
    }

    /** Thả một người vào ô. */
    public function store(Request $request, Placement $placement): JsonResponse
    {
        $data = $request->validate([
            'user' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'slot' => ['required', 'string', 'max:20'],
            'lane' => ['required', 'string', 'max:40'],
        ]);
        $date = $this->date($data['date']);
        $user = Roster::users()->get((int) $data['user']) ?? abort(422);

        $result = $placement->check($user, $date, $data['slot'], $data['lane']);

        if ($result['error']) {
            return response()->json(['message' => $result['error']], 422);
        }

        $row = Assignment::create([
            'work_date' => $date->toDateString(),
            'shift_id' => $data['slot'] === Roster::WHOLE_DAY ? null : (int) $data['slot'],
            'user_id' => $user->id,
            ...Roster::laneColumns($data['lane']),
        ]);

        return $this->placed($row, $user, $result['warnings']);
    }

    /** Kéo một người từ ô này sang ô khác. */
    public function update(Request $request, Assignment $assignment, Placement $placement): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'slot' => ['required', 'string', 'max:20'],
            'lane' => ['required', 'string', 'max:40'],
        ]);
        $date = $this->date($data['date']);
        $user = Roster::users()->get($assignment->user_id) ?? abort(422);

        $result = $placement->check($user, $date, $data['slot'], $data['lane'], $assignment);

        if ($result['error']) {
            return response()->json(['message' => $result['error']], 422);
        }

        $assignment->update([
            'work_date' => $date->toDateString(),
            'shift_id' => $data['slot'] === Roster::WHOLE_DAY ? null : (int) $data['slot'],
            ...Roster::laneColumns($data['lane']),
        ]);

        return $this->placed($assignment, $user, $result['warnings']);
    }

    public function destroy(Assignment $assignment): JsonResponse
    {
        $user = Roster::users()->get($assignment->user_id);
        $assignment->delete();

        return response()->json(['stats' => $user ? $this->stats(collect([$user->id => $user]), Roster::week($assignment->work_date->toImmutable())) : []]);
    }

    /** Chép phân công tuần trước sang tuần đang xem, thay phân công đã có của tuần này. */
    public function copyWeek(Request $request): RedirectResponse
    {
        $days = Roster::week($this->date($request->input('date')));
        $previous = Roster::assignments($days[0]->subWeek(), $days[6]->subWeek());

        DB::transaction(function () use ($days, $previous): void {
            Assignment::whereBetween('work_date', [$days[0]->toDateString(), $days[6]->toDateString()])->delete();

            foreach ($previous as $row) {
                Assignment::create([
                    'work_date' => $row->work_date->addWeek()->toDateString(),
                    ...$row->only(['shift_id', 'dinein_station_id', 'area_id', 'schedule_task_id', 'user_id']),
                ]);
            }
        });

        ActivityLog::record(tenant()->getKey(), 'schedule', __('schedule.copy_log', ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m')]));

        return redirect()->route('tenant.admin.schedule.assign', ['date' => $days[0]->toDateString()])
            ->with('status', trans_choice('schedule.copied', $previous->count(), ['count' => $previous->count()]));
    }

    /**
     * Áp lịch mặc định vào tuần: thêm người mặc định còn thiếu vào từng ô, không bỏ ai
     * đang có. Bỏ qua người đã nghỉ việc và người có đơn nghỉ đã duyệt vào ô đó.
     */
    public function applyDefaults(Request $request): RedirectResponse
    {
        $days = Roster::week($this->date($request->input('date')));
        $shifts = Roster::shiftsByWeekday();
        $users = Roster::users();
        $lanes = Roster::lanes(Roster::targets());
        $defaults = DefaultSlot::all()->groupBy('weekday');
        $existing = Roster::grid(Roster::assignments($days[0], $days[6]));
        $leaves = LeaveRequest::where('status', LeaveStatus::Approved)->activeBetween($days[0], $days[6])->get()->groupBy('user_id');
        $added = 0;
        $skipped = 0;

        DB::transaction(function () use ($days, $shifts, $users, $lanes, $defaults, $existing, $leaves, &$added, &$skipped): void {
            foreach ($days as $day) {
                $slotKeys = array_column(Roster::slots($day, $shifts), 'key');

                foreach ($defaults->get($day->dayOfWeekIso, collect()) as $default) {
                    $slot = $default->slotKey();
                    $lane = $default->laneKey();

                    if (! in_array($slot, $slotKeys, true) || ! isset($lanes[$lane])) {
                        continue;
                    }

                    $present = collect($existing[$day->toDateString()][$slot][$lane] ?? [])->pluck('user_id')->all();

                    foreach ($default->user_ids ?? [] as $userId) {
                        if (in_array($userId, $present, true) || ! $users->has($userId)) {
                            continue;
                        }

                        if ($leaves->get($userId, collect())->contains(fn (LeaveRequest $leave) => $leave->covers($day, $slot))) {
                            $skipped++;

                            continue;
                        }

                        Assignment::create([
                            'work_date' => $day->toDateString(),
                            'shift_id' => $slot === Roster::WHOLE_DAY ? null : (int) $slot,
                            'user_id' => $userId,
                            ...Roster::laneColumns($lane),
                        ]);
                        $added++;
                    }
                }
            }
        });

        ActivityLog::record(tenant()->getKey(), 'schedule', __('schedule.defaults_applied_log', ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m'), 'count' => $added]));

        $message = trans_choice('schedule.defaults_applied', $added, ['count' => $added]);
        if ($skipped > 0) {
            $message .= ' '.__('schedule.defaults_skipped', ['count' => $skipped]);
        }

        return redirect()->route('tenant.admin.schedule.assign', ['date' => $days[0]->toDateString()])->with('status', $message);
    }

    /** @param list<string> $warnings */
    private function placed(Assignment $row, User $user, array $warnings): JsonResponse
    {
        $days = Roster::week($row->work_date->toImmutable());

        return response()->json(['id' => $row->id, 'warnings' => $warnings, 'stats' => $this->stats(collect([$user->id => $user]), $days)]);
    }

    /**
     * Số ca, số giờ tuần này của từng người, so với giờ hợp đồng. Hợp đồng theo
     * tháng thì so giờ đã xếp trong tháng (tháng của thứ năm trong tuần).
     *
     * @param  Collection<int, User>  $users
     * @param  list<CarbonImmutable>  $days
     * @return array<int, array{short: string, label: string, over: bool}>
     */
    private function stats(Collection $users, array $days): array
    {
        $shifts = Roster::shiftsByWeekday();
        $week = Roster::totals(Roster::assignments($days[0], $days[6])->whereIn('user_id', $users->keys()), $shifts);
        $month = $days[3];
        $monthTotals = $users->contains(fn (User $user) => $user->contract_period === 'month')
            ? Roster::totals(Roster::assignments($month->startOfMonth(), $month->endOfMonth())->whereIn('user_id', $users->keys()), $shifts)
            : [];

        $stats = [];
        foreach ($users as $user) {
            $total = $week[$user->id] ?? ['shifts' => 0, 'minutes' => 0];
            $monthly = $user->contract_period === 'month';
            $limit = $user->contract_hours !== null ? (int) round($user->contract_hours * 60) : null;
            $used = $monthly ? ($monthTotals[$user->id]['minutes'] ?? 0) : $total['minutes'];

            $stats[$user->id] = Roster::stat($total['shifts'], $total['minutes'], $limit, $monthly, $used, $month->month);
        }

        return $stats;
    }

    /** @param list<CarbonImmutable> $days */
    private function slotsOf(array $days, array $shifts): array
    {
        return collect($days)->mapWithKeys(fn (CarbonImmutable $day) => [$day->toDateString() => Roster::slots($day, $shifts)])->all();
    }

    /** Cho kéo thả: tô ô đỏ (đã duyệt nghỉ) hoặc vàng (đang xin nghỉ) khi kéo người đó. */
    private function leaveMap(array $leaves): object
    {
        return (object) collect($leaves)->map(fn (array $list) => array_map(fn ($leave) => [
            'from' => $leave->starts_on->toDateString(),
            'to' => $leave->ends_on->toDateString(),
            'shifts' => $leave->shift_ids,
            'approved' => $leave->status === LeaveStatus::Approved,
        ], $list))->all();
    }

    private function date(mixed $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) ($value ?: 'today'))->startOfDay();
        } catch (Throwable) {
            return CarbonImmutable::today();
        }
    }
}
