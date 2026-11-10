<?php

namespace App\Modules\Schedule\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Lịch làm việc: xếp người vào khu (bếp, bar, khu bàn) theo ngày và ca làm.
 * Xem theo tuần, theo ngày (để xếp), hoặc theo một người.
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

        return view('modules.schedule.week', [
            'date' => $days[0],
            'days' => $days,
            'slots' => collect($days)->mapWithKeys(fn (CarbonImmutable $day) => [$day->toDateString() => Roster::slots($day, $shifts)])->all(),
            'grid' => Roster::grid($days[0], $days[6]),
            'targets' => Roster::targets(),
            'users' => Roster::users(),
        ]);
    }

    public function day(Request $request): View
    {
        $date = $this->date($request->query('date'));
        $slots = Roster::slots($date, Roster::shiftsByWeekday());
        $grid = Roster::grid($date, $date)[$date->toDateString()] ?? [];
        $users = Roster::users();

        // Một người ở hai khu trong cùng một ca thì nhắc.
        $conflicts = [];
        foreach ($slots as $slot) {
            $counts = array_count_values(array_merge(...array_values($grid[$slot['key']] ?? [[]])));
            foreach (array_keys(array_filter($counts, fn (int $count) => $count > 1)) as $userId) {
                if ($users->has($userId)) {
                    $conflicts[] = __('schedule.conflict', ['name' => $users[$userId]->name, 'slot' => $slot['name']]);
                }
            }
        }

        return view('modules.schedule.day', [
            'date' => $date,
            'slots' => $slots,
            'grid' => $grid,
            'targets' => Roster::targets(),
            'users' => $users,
            'conflicts' => $conflicts,
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
        $targets = Roster::targets();
        $grid = Roster::grid($days[0], $days[6]);

        $schedule = [];
        $minutes = 0;
        foreach ($days as $day) {
            $entries = [];
            foreach (Roster::slots($day, $shifts) as $slot) {
                foreach ($grid[$day->toDateString()][$slot['key']] ?? [] as $targetKey => $userIds) {
                    if (in_array($person->id, $userIds, true) && $targets->has($targetKey)) {
                        $entries[] = ['slot' => $slot, 'target' => $targets[$targetKey]];
                    }
                }
            }
            // Ở hai khu cùng một ca chỉ tính giờ một lần.
            $minutes += collect($entries)->unique(fn ($entry) => $entry['slot']['key'])->sum(fn ($entry) => $entry['slot']['minutes']);
            $schedule[$day->toDateString()] = $entries;
        }

        return view('modules.schedule.person', [
            'date' => $days[0],
            'days' => $days,
            'person' => $person,
            'users' => $users,
            'schedule' => $schedule,
            'minutes' => $minutes,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $date = $this->date($request->input('date'));
        $slots = collect(Roster::slots($date, Roster::shiftsByWeekday()))->keyBy('key');
        $targets = Roster::targets();

        $data = $request->validate([
            'slots' => ['array'],
            'slots.*' => ['array'],
            'slots.*.*' => ['array'],
            'slots.*.*.*' => ['integer', Rule::in(Roster::users()->keys()->all())],
        ]);

        $rows = [];
        foreach ($data['slots'] ?? [] as $slotKey => $byTarget) {
            if (! $slots->has((string) $slotKey)) {
                continue;
            }

            foreach ($byTarget as $targetKey => $userIds) {
                if (! $targets->has($targetKey)) {
                    continue;
                }

                foreach (array_unique(array_map('intval', $userIds)) as $userId) {
                    $rows[] = $this->row($date, (string) $slotKey, $targetKey, $userId);
                }
            }
        }

        DB::transaction(function () use ($date, $rows): void {
            Assignment::whereDate('work_date', $date)->delete();

            foreach ($rows as $row) {
                Assignment::create($row);
            }
        });

        ActivityLog::record(tenant()->getKey(), 'schedule', __('schedule.day_log', ['date' => $this->label($date)]));

        return redirect()->route('tenant.admin.schedule.day', ['date' => $date->toDateString()])->with('status', __('schedule.saved'));
    }

    /** Chép phân công tuần trước sang tuần đang xem, thay phân công đã có của tuần này. */
    public function copyWeek(Request $request): RedirectResponse
    {
        $days = Roster::week($this->date($request->input('date')));
        $previous = Assignment::whereBetween('work_date', [$days[0]->subWeek()->toDateString(), $days[6]->subWeek()->toDateString()])->get();

        DB::transaction(function () use ($days, $previous): void {
            Assignment::whereBetween('work_date', [$days[0]->toDateString(), $days[6]->toDateString()])->delete();

            foreach ($previous as $row) {
                Assignment::create([
                    'work_date' => $row->work_date->addWeek()->toDateString(),
                    'shift_id' => $row->shift_id,
                    'dinein_station_id' => $row->dinein_station_id,
                    'area_id' => $row->area_id,
                    'user_id' => $row->user_id,
                ]);
            }
        });

        ActivityLog::record(tenant()->getKey(), 'schedule', __('schedule.copy_log', ['from' => $this->label($days[0]), 'to' => $this->label($days[6])]));

        return redirect()->route('tenant.admin.schedule.week', ['date' => $days[0]->toDateString()])
            ->with('status', trans_choice('schedule.copied', $previous->count(), ['count' => $previous->count()]));
    }

    /** @return array<string, mixed> */
    private function row(CarbonImmutable $date, string $slotKey, string $targetKey, int $userId): array
    {
        return [
            'work_date' => $date->toDateString(),
            'shift_id' => $slotKey === Roster::WHOLE_DAY ? null : (int) $slotKey,
            'dinein_station_id' => str_starts_with($targetKey, 's') ? (int) substr($targetKey, 1) : null,
            'area_id' => str_starts_with($targetKey, 'a') ? (int) substr($targetKey, 1) : null,
            'user_id' => $userId,
        ];
    }

    private function label(CarbonImmutable $date): string
    {
        return __('ui.calendar.weekdays')[$date->dayOfWeekIso - 1].' '.$date->format('d/m');
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
