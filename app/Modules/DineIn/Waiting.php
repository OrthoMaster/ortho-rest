<?php

namespace App\Modules\DineIn;

/** Viết số phút cho người đọc: "25 phút", "1 giờ 20 phút". */
final class Waiting
{
    public static function text(int $minutes): string
    {
        $minutes = abs($minutes);

        if ($minutes < 60) {
            return __('dinein.duration_minutes', ['count' => $minutes]);
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0
            ? __('dinein.duration_hours_only', ['hours' => $hours])
            : __('dinein.duration_hours', ['hours' => $hours, 'minutes' => $rest]);
    }
}
