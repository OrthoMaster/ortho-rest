<?php

namespace App\Modules\Schedule;

use App\Enums\ShiftType;
use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\Models\Station;
use App\Modules\Reservation\Models\Area;
use App\Modules\Schedule\Models\Assignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Dữ liệu của lịch phân công: khu (bếp, bar rồi khu bàn), ca làm của từng ngày,
 * và ai được xếp vào khu nào trong ca nào.
 */
class Roster
{
    /** Khoá của "cả ngày" khi ngày đó chưa khai ca làm. */
    public const WHOLE_DAY = 'day';

    /** @return Collection<string, array{key: string, kind: string, name: string, code: ?string}> s12 = khu phục vụ, a3 = khu bàn */
    public static function targets(): Collection
    {
        $targets = collect();

        if (has_module('dinein')) {
            foreach (Station::orderBy('sort_order')->orderBy('id')->get() as $station) {
                $targets->put('s'.$station->id, ['key' => 's'.$station->id, 'kind' => 'station', 'name' => $station->name, 'code' => null]);
            }
        }

        foreach (Area::where('is_active', true)->whereNotNull('code')->orderBy('sort_order')->orderBy('id')->get() as $area) {
            $targets->put('a'.$area->id, ['key' => 'a'.$area->id, 'kind' => 'area', 'name' => $area->name, 'code' => $area->code]);
        }

        return $targets;
    }

    /** @return Collection<int, User> người đang làm ở quán */
    public static function users(): Collection
    {
        return tenant()->users()->where('is_active', true)->orderBy('name')->get()->keyBy('id');
    }

    /** @return array<int, Collection<int, Shift>> ca làm theo thứ (1 = thứ hai) */
    public static function shiftsByWeekday(): array
    {
        $shifts = Shift::where('type', ShiftType::Work)->orderBy('start_time')->get()->groupBy('weekday');

        return collect(range(1, 7))->mapWithKeys(fn (int $day) => [$day => $shifts->get($day, collect())->values()])->all();
    }

    /**
     * Các "ô thời gian" của một ngày: từng ca làm, hoặc cả ngày nếu chưa khai ca.
     *
     * @param  array<int, Collection<int, Shift>>  $shifts
     * @return list<array{key: string, shift: ?Shift, name: string, time: ?string, minutes: int}>
     */
    public static function slots(CarbonImmutable $date, array $shifts): array
    {
        $dayShifts = $shifts[$date->dayOfWeekIso] ?? collect();

        if ($dayShifts->isEmpty()) {
            return [['key' => self::WHOLE_DAY, 'shift' => null, 'name' => __('schedule.whole_day'), 'time' => null, 'minutes' => 0]];
        }

        return $dayShifts->map(fn (Shift $shift) => [
            'key' => (string) $shift->id,
            'shift' => $shift,
            'name' => $shift->name,
            'time' => $shift->timeRange(),
            'minutes' => self::minutes($shift),
        ])->all();
    }

    /**
     * Phân công trong khoảng ngày: [ngày][ô thời gian][khu] => danh sách user id.
     *
     * @return array<string, array<string, array<string, list<int>>>>
     */
    public static function grid(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $grid = [];

        Assignment::whereBetween('work_date', [$from->toDateString(), $to->toDateString()])->orderBy('id')->get()
            ->each(function (Assignment $row) use (&$grid) {
                $slot = $row->shift_id ? (string) $row->shift_id : self::WHOLE_DAY;
                $target = $row->dinein_station_id ? 's'.$row->dinein_station_id : 'a'.$row->area_id;
                $grid[$row->work_date->toDateString()][$slot][$target][] = $row->user_id;
            });

        return $grid;
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
}
