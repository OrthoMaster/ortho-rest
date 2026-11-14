<?php

namespace App\Modules\Schedule;

use App\Models\Shift;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\Week;
use App\Push\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Thông báo đẩy của Lịch làm việc khi sửa lịch:
 * - Lịch đã công bố bị sửa (thêm, bớt, đổi ô): báo người đó. Các thay đổi trong 10 phút gom vào một thông báo
 *   (cùng tag nên máy thay thông báo cũ bằng bản mới liệt kê đủ các thay đổi).
 * - Duyệt nghỉ làm ca không còn đủ số người cần (theo lịch mặc định): báo người xếp lịch.
 */
class ScheduleAlerts
{
    private const GATHER_MINUTES = 10;

    private const MAX_LINES = 6;

    /** Một ô của lịch: "T3 10/11 · Ca tối · Bếp". */
    public static function describe(Assignment $row): string
    {
        $date = $row->work_date->toImmutable();
        $shift = $row->shift_id ? Shift::find($row->shift_id)?->name : __('schedule.whole_day');
        $lane = Roster::lanes(Roster::targets())[Roster::laneKey($row)] ?? null;
        $place = $lane ? $lane['target']['name'].($lane['lane']['task_id'] ? ' · '.$lane['lane']['name'] : '') : null;

        return implode(' · ', array_filter([__('ui.calendar.weekdays_short')[$date->dayOfWeekIso - 1].' '.$date->format('d/m'), $shift, $place]));
    }

    /**
     * Người xếp lịch thêm, bỏ, đổi ô một người ở tuần đã công bố (từ hôm nay trở đi).
     *
     * @param  'added'|'removed'  $change
     */
    public static function changed(Assignment $row, string $change): void
    {
        $date = $row->work_date->toImmutable();

        if ($date->lt(today()) || ! Week::isPublishedOn($date)) {
            return;
        }

        $week = Roster::week($date)[0];
        $key = 'schedule-changes:'.tenant()->getKey().':'.$row->user_id.':'.$week->toDateString();
        $lines = array_slice([...Cache::get($key, []), __('schedule.push_change_'.$change, ['slot' => self::describe($row)])], -self::MAX_LINES);
        Cache::put($key, $lines, now()->addMinutes(self::GATHER_MINUTES));

        $user = tenant()->users()->find($row->user_id);

        if ($user) {
            Notify::users([$user], [
                'title' => __('schedule.push_changed'),
                'body' => implode("\n", $lines),
                'url' => route('tenant.admin.schedule.week', ['date' => $week->toDateString()]),
                'tag' => 'schedule-change-'.$row->user_id.'-'.$week->toDateString(),
            ]);
        }
    }

    /**
     * Sau khi bỏ một người khỏi các ô (duyệt nghỉ): ô nào từ hôm nay trở đi còn ít người hơn số cần thì báo người xếp lịch.
     *
     * @param  Collection<int, Assignment>  $removed
     */
    public static function shortAfterLeave(Collection $removed, string $name): void
    {
        $needed = Roster::neededByWeekday();
        $lines = [];

        foreach ($removed as $row) {
            $date = $row->work_date->toImmutable();
            $slot = $row->shift_id ? (string) $row->shift_id : Roster::WHOLE_DAY;
            $lane = Roster::laneKey($row);
            $need = $needed[$date->dayOfWeekIso][$slot][$lane] ?? 0;

            if ($need === 0 || $date->lt(today())) {
                continue;
            }

            $left = Assignment::whereDate('work_date', $date->toDateString())->where('shift_id', $row->shift_id)
                ->where(Roster::laneColumns($lane))->count();

            if ($left < $need) {
                $lines[] = __('schedule.push_short_line', ['slot' => self::describe($row), 'left' => $left, 'need' => $need]);
            }
        }

        if ($lines === []) {
            return;
        }

        $first = $removed->min('work_date');
        Notify::permitted('schedule.manage', [
            'title' => __('schedule.push_short', ['name' => $name]),
            'body' => implode("\n", array_slice($lines, 0, self::MAX_LINES)),
            'url' => route('tenant.admin.schedule.assign', ['date' => CarbonImmutable::parse($first)->toDateString()]),
            'tag' => 'schedule-short-'.$removed->first()->user_id,
        ]);
    }
}
