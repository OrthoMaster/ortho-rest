<?php

namespace App\Modules\Schedule;

use App\Enums\ShiftType;
use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\Models\Station;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Dữ liệu của lịch làm việc.
 *
 * - Khu: khu phục vụ (s12), khu bàn (a3), và nhóm "Việc chung" (g) cho việc không thuộc khu nào.
 * - Hàng (lane): mỗi khu có một hàng chung ("s12") và một hàng cho mỗi việc của khu ("s12-t5").
 * - Ô thời gian (slot): mỗi ca làm của ngày, hoặc cả ngày nếu ngày đó chưa khai ca.
 */
class Roster
{
    /** Khoá của "cả ngày" khi ngày đó chưa khai ca làm. */
    public const WHOLE_DAY = 'day';

    public const GENERAL = 'g';

    /**
     * @return Collection<string, array{key: string, kind: string, name: string, code: ?string, department_id: ?int, lanes: list<array{key: string, task_id: ?int, name: string}>}>
     */
    public static function targets(): Collection
    {
        $tasks = Task::orderBy('sort_order')->orderBy('id')->get()->groupBy(fn (Task $task) => $task->targetKey());
        $targets = collect();
        $add = function (string $key, string $kind, string $name, ?string $code, ?int $department) use ($targets, $tasks): void {
            $lanes = $key === self::GENERAL ? [] : [['key' => $key, 'task_id' => null, 'name' => __('schedule.lane_general')]];

            foreach ($tasks->get($key, collect()) as $task) {
                $lanes[] = ['key' => $key.'-t'.$task->id, 'task_id' => $task->id, 'name' => $task->name];
            }

            if ($lanes !== []) {
                $targets->put($key, compact('key', 'kind', 'name', 'code') + ['department_id' => $department, 'lanes' => $lanes]);
            }
        };

        if (has_module('dinein')) {
            foreach (Station::orderBy('sort_order')->orderBy('id')->get() as $station) {
                $add('s'.$station->id, 'station', $station->name, null, $station->department_id);
            }
        }

        foreach (Area::where('is_active', true)->whereNotNull('code')->orderBy('sort_order')->orderBy('id')->get() as $area) {
            $add('a'.$area->id, 'area', $area->name, $area->code, $area->department_id);
        }

        $add(self::GENERAL, 'general', __('schedule.general_tasks'), null, null);

        return $targets;
    }

    /** @return array<string, array{target: array<string, mixed>, lane: array{key: string, task_id: ?int, name: string}}> mọi hàng theo khoá */
    public static function lanes(Collection $targets): array
    {
        $lanes = [];

        foreach ($targets as $target) {
            foreach ($target['lanes'] as $lane) {
                $lanes[$lane['key']] = ['target' => $target, 'lane' => $lane];
            }
        }

        return $lanes;
    }

    /** @return array{dinein_station_id: ?int, area_id: ?int, schedule_task_id: ?int} cột trong bảng phân công của một hàng */
    public static function laneColumns(string $laneKey): array
    {
        [$target, $task] = array_pad(explode('-t', $laneKey, 2), 2, null);

        return [
            'dinein_station_id' => str_starts_with($target, 's') ? (int) substr($target, 1) : null,
            'area_id' => str_starts_with($target, 'a') ? (int) substr($target, 1) : null,
            'schedule_task_id' => $task !== null ? (int) $task : null,
        ];
    }

    public static function laneKey(Assignment $row): string
    {
        $target = match (true) {
            $row->dinein_station_id !== null => 's'.$row->dinein_station_id,
            $row->area_id !== null => 'a'.$row->area_id,
            default => self::GENERAL,
        };

        return $row->schedule_task_id ? $target.'-t'.$row->schedule_task_id : $target;
    }

    /** @return Collection<int, User> người đang làm ở quán, kèm bộ phận */
    public static function users(): Collection
    {
        return tenant()->users()->with('department')->where('is_active', true)
            ->get()
            ->sortBy([
                fn (User $a, User $b) => ($a->department?->sort_order ?? PHP_INT_MAX) <=> ($b->department?->sort_order ?? PHP_INT_MAX),
                fn (User $a, User $b) => strcmp($a->name, $b->name),
            ])
            ->keyBy('id');
    }

    /** @return array<int, Collection<int, Shift>> ca làm theo thứ (1 = thứ hai) */
    public static function shiftsByWeekday(): array
    {
        $shifts = Shift::where('type', ShiftType::Work)->orderBy('start_time')->get()->groupBy('weekday');

        return collect(range(1, 7))->mapWithKeys(fn (int $day) => [$day => $shifts->get($day, collect())->values()])->all();
    }

    /** Số giờ một ngày công: quy ca ra ngày phép, và giờ của "cả ngày". */
    public static function workdayMinutes(): int
    {
        $hours = (float) (app(ModuleManager::class)->settings(tenant(), 'schedule')['workday_hours'] ?? 8);

        return (int) round($hours * 60);
    }

    /**
     * Các ô thời gian của một ngày: từng ca làm, hoặc cả ngày nếu chưa khai ca.
     *
     * @param  array<int, Collection<int, Shift>>  $shifts
     * @return list<array{key: string, shift: ?Shift, name: string, time: ?string, minutes: int}>
     */
    public static function slots(CarbonImmutable $date, array $shifts): array
    {
        $dayShifts = $shifts[$date->dayOfWeekIso] ?? collect();

        if ($dayShifts->isEmpty()) {
            return [['key' => self::WHOLE_DAY, 'shift' => null, 'name' => __('schedule.whole_day'), 'time' => null, 'minutes' => self::workdayMinutes()]];
        }

        return $dayShifts->map(fn (Shift $shift) => [
            'key' => (string) $shift->id,
            'shift' => $shift,
            'name' => $shift->name,
            'time' => $shift->timeRange(),
            'minutes' => self::minutes($shift),
        ])->all();
    }

    /** @return Collection<int, Assignment> phân công trong khoảng ngày */
    public static function assignments(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Assignment::whereBetween('work_date', [$from->toDateString(), $to->toDateString()])->orderBy('id')->get();
    }

    /**
     * Phân công xếp theo [ngày][ô thời gian][hàng] => danh sách phân công.
     *
     * @return array<string, array<string, array<string, list<Assignment>>>>
     */
    public static function grid(Collection $assignments): array
    {
        $grid = [];

        foreach ($assignments as $row) {
            $grid[$row->work_date->toDateString()][$row->shift_id ? (string) $row->shift_id : self::WHOLE_DAY][self::laneKey($row)][] = $row;
        }

        return $grid;
    }

    /**
     * Số ca và số phút đã xếp của từng người trong các phân công này. Một người ở
     * hai hàng trong cùng một ca chỉ tính một ca.
     *
     * @return array<int, array{shifts: int, minutes: int}>
     */
    public static function totals(Collection $assignments, array $shifts): array
    {
        $whole = self::workdayMinutes();
        $minutes = collect($shifts)->flatten(1)->mapWithKeys(fn (Shift $shift) => [$shift->id => self::minutes($shift)]);
        $totals = [];

        foreach ($assignments->unique(fn (Assignment $row) => $row->user_id.'|'.$row->work_date->toDateString().'|'.$row->shift_id) as $row) {
            $totals[$row->user_id]['shifts'] = ($totals[$row->user_id]['shifts'] ?? 0) + 1;
            $totals[$row->user_id]['minutes'] = ($totals[$row->user_id]['minutes'] ?? 0) + ($row->shift_id ? (int) $minutes->get($row->shift_id, 0) : $whole);
        }

        return $totals;
    }

    /** @return list<CarbonImmutable> thứ hai đến chủ nhật của tuần chứa ngày này */
    public static function week(CarbonImmutable $date): array
    {
        $monday = $date->startOfWeek(CarbonImmutable::MONDAY);

        return array_map(fn (int $offset) => $monday->addDays($offset), range(0, 6));
    }

    public static function minutes(Shift $shift): int
    {
        $toMinutes = function (string $time): int {
            [$hour, $minute] = array_map('intval', explode(':', $time));

            return $hour * 60 + $minute;
        };
        $length = $toMinutes($shift->end_time) - $toMinutes($shift->start_time);

        return $length <= 0 ? $length + 1440 : $length;
    }

    /** 9,5 giờ: giờ dạng chữ, bỏ số 0 thừa. */
    public static function hours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 1, ',', '.'), '0'), ',');
    }
}
