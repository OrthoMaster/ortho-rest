<?php

namespace App\Modules\Schedule\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Models\Assignment;
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
 * Lịch làm việc theo tuần. Người xếp lịch kéo tên nhân viên vào ô (hàng khu hoặc
 * việc × ca × ngày), mỗi lần thả lưu ngay. Người chỉ xem thấy cùng lịch, không kéo được.
 */
class ScheduleController extends Controller
{
    public function home(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->can('schedule.view') ? 'tenant.admin.schedule.week' : 'tenant.admin.schedule.person');
    }

    public function week(Request $request): View
    {
        $days = Roster::week($this->date($request->query('date')));
        $shifts = Roster::shiftsByWeekday();
        $assignments = Roster::assignments($days[0], $days[6]);
        $users = Roster::users();
        $editable = $request->user()->can('schedule.manage');
        $leaves = $editable ? Placement::leavesInWeek($days) : [];

        return view('modules.schedule.week', [
            'date' => $days[0],
            'days' => $days,
            'slots' => collect($days)->mapWithKeys(fn (CarbonImmutable $day) => [$day->toDateString() => Roster::slots($day, $shifts)])->all(),
            'grid' => Roster::grid($assignments),
            'targets' => Roster::targets(),
            'users' => $users,
            'editable' => $editable,
            'stats' => $editable ? $this->stats($users, $days) : [],
            'leaves' => $leaves,
            // Cho kéo thả: tô ô đỏ (đã duyệt nghỉ) hoặc vàng (đang xin nghỉ) khi kéo người đó.
            'leaveMap' => (object) collect($leaves)->map(fn (array $list) => array_map(fn ($leave) => [
                'from' => $leave->starts_on->toDateString(),
                'to' => $leave->ends_on->toDateString(),
                'shifts' => $leave->shift_ids,
                'approved' => $leave->status === LeaveStatus::Approved,
            ], $list))->all(),
        ]);
    }

    public function person(Request $request): View
    {
        $viewer = $request->user();
        $users = Roster::users();
        // Không có quyền xem lịch mọi người thì chỉ thấy lịch của mình.
        $userId = $viewer->can('schedule.view') ? (int) $request->query('user', $viewer->id) : $viewer->id;
        $person = $users->get($userId) ?? $viewer;
        $days = Roster::week($this->date($request->query('date')));
        $shifts = Roster::shiftsByWeekday();
        $lanes = Roster::lanes(Roster::targets());
        $mine = Roster::assignments($days[0], $days[6])->where('user_id', $person->id);
        $grid = Roster::grid($mine);

        $schedule = [];
        foreach ($days as $day) {
            $entries = [];
            foreach (Roster::slots($day, $shifts) as $slot) {
                foreach (array_keys($grid[$day->toDateString()][$slot['key']] ?? []) as $laneKey) {
                    if (isset($lanes[$laneKey])) {
                        $entries[] = ['slot' => $slot] + $lanes[$laneKey];
                    }
                }
            }
            $schedule[$day->toDateString()] = $entries;
        }

        $total = Roster::totals($mine, $shifts)[$person->id] ?? ['shifts' => 0, 'minutes' => 0];

        return view('modules.schedule.person', [
            'date' => $days[0],
            'days' => $days,
            'person' => $person,
            'users' => $users,
            'schedule' => $schedule,
            'total' => $total,
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

        return redirect()->route('tenant.admin.schedule.week', ['date' => $days[0]->toDateString()])
            ->with('status', trans_choice('schedule.copied', $previous->count(), ['count' => $previous->count()]));
    }

    /** @param list<string> $warnings */
    private function placed(Assignment $row, User $user, array $warnings): JsonResponse
    {
        $days = Roster::week($row->work_date->toImmutable());
        $stats = $this->stats(collect([$user->id => $user]), $days);

        return response()->json(['id' => $row->id, 'warnings' => $warnings, 'stats' => $stats]);
    }

    /**
     * Số ca, số giờ tuần này của từng người, so với giờ hợp đồng. Hợp đồng theo
     * tháng thì so giờ đã xếp trong tháng (tháng của thứ năm trong tuần).
     *
     * @param  Collection<int, User>  $users
     * @param  list<CarbonImmutable>  $days
     * @return array<int, array{shifts: int, hours: string, label: string, over: bool}>
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
            $hours = Roster::hours($total['minutes']);
            $label = __('schedule.stat_week', ['shifts' => $total['shifts'], 'hours' => $hours]);
            $over = false;

            if ($user->contract_hours !== null) {
                $limit = (int) round($user->contract_hours * 60);
                $monthly = $user->contract_period === 'month';
                $used = $monthly ? ($monthTotals[$user->id]['minutes'] ?? 0) : $total['minutes'];
                $label = __($monthly ? 'schedule.stat_contract_month' : 'schedule.stat_contract_week', [
                    'shifts' => $total['shifts'],
                    'hours' => $hours,
                    'used' => Roster::hours($used),
                    'limit' => Roster::hours($limit),
                    'month' => $month->month,
                ]);
                $over = $used > $limit;
            }

            $stats[$user->id] = ['shifts' => $total['shifts'], 'hours' => $hours, 'label' => $label, 'over' => $over];
        }

        return $stats;
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
