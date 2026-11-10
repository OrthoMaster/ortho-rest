<?php

namespace App\Modules\Schedule;

use App\Enums\ShiftType;
use App\Models\Shift;
use App\Models\User;
use App\Modules\Schedule\Models\Assignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ai đang làm ở khu nào lúc này: theo phân công của ngày và ca làm đang diễn ra.
 * Ca kéo qua nửa đêm thì sau 0 giờ vẫn tính là ca của hôm trước. Ngày chưa khai
 * ca làm thì phân công cả ngày.
 */
class Duty
{
    /** @return list<array{date: string, shift: ?int}> các ca đang diễn ra, kèm "cả ngày" của hôm nay */
    public static function slots(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $slots = [['date' => $now->toDateString(), 'shift' => null]];

        foreach ([$now->startOfDay(), $now->subDay()->startOfDay()] as $day) {
            $shifts = Shift::where('type', ShiftType::Work)->where('weekday', $day->dayOfWeekIso)->get();

            foreach ($shifts as $shift) {
                $starts = $day->setTimeFromTimeString($shift->start_time);
                $ends = $day->setTimeFromTimeString($shift->end_time)->addDays($shift->crossesMidnight() ? 1 : 0);

                if ($now->gte($starts) && $now->lt($ends)) {
                    $slots[] = ['date' => $day->toDateString(), 'shift' => $shift->id];
                }
            }
        }

        return $slots;
    }

    /** Phân công đang có hiệu lực lúc này. */
    public static function current(?CarbonImmutable $now = null): Builder
    {
        $slots = self::slots($now);

        return Assignment::query()->where(function (Builder $query) use ($slots) {
            foreach ($slots as $slot) {
                $query->orWhere(fn (Builder $query) => $query->whereDate('work_date', $slot['date'])->where('shift_id', $slot['shift']));
            }
        });
    }

    /** Tắt Lịch làm việc thì không ai được tính là đang làm theo phân công. */
    private static function active(User $user): bool
    {
        return (bool) $user->restaurant?->hasModule('schedule');
    }

    /** @return list<int> khu (bếp, bar) người này đang làm */
    public static function stationIds(User $user): array
    {
        if (! self::active($user)) {
            return [];
        }

        return self::current()->where('user_id', $user->id)->whereNotNull('dinein_station_id')->pluck('dinein_station_id')->unique()->values()->all();
    }

    /** @return list<int> khu vực bàn người này đang phục vụ */
    public static function areaIds(User $user): array
    {
        if (! self::active($user)) {
            return [];
        }

        return self::current()->where('user_id', $user->id)->whereNotNull('area_id')->pluck('area_id')->unique()->values()->all();
    }

    /** Có được mở màn hình của khu này không. */
    public static function canOpenStation(User $user, int $stationId): bool
    {
        return $user->can('dinein.stations') || in_array($stationId, self::stationIds($user), true);
    }

    /** @return Collection<int, Shift> ca làm của một ngày, theo giờ bắt đầu */
    public static function shiftsOn(CarbonImmutable $date): Collection
    {
        return Shift::where('type', ShiftType::Work)->where('weekday', $date->dayOfWeekIso)->orderBy('start_time')->get();
    }
}
