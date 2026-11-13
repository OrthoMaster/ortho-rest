<?php

namespace App\Modules\DineIn;

use App\Models\User;
use App\Modules\ModuleManager;
use App\Modules\Schedule\Duty;

/**
 * Giới hạn giờ lấy đơn (Cài đặt chung, khi bật Lấy đơn tại bàn và Lịch làm việc): nhân viên
 * chỉ mở bàn, gọi món, đóng bàn khi đang trong ca làm của mình, mở sớm 15 / 30 / 60 phút trước
 * giờ vào ca. Chủ quán và người có quyền "Lấy đơn ngoài giờ làm" không bị giới hạn.
 */
final class OrderWindow
{
    public const MINUTES = [15, 30, 60];

    /** Số phút mở trước ca, null là không giới hạn. */
    public static function minutes(): ?int
    {
        if (! tenant() || ! has_module('dinein') || ! has_module('schedule')) {
            return null;
        }

        $minutes = app(ModuleManager::class)->settings(tenant(), 'dinein')['staff_window'] ?? null;

        return in_array($minutes, self::MINUTES, true) ? $minutes : null;
    }

    /** Lý do chưa được lấy đơn lúc này, null là được. */
    public static function blocked(User $user): ?string
    {
        $minutes = self::minutes();

        if ($minutes === null || $user->can('dinein.anytime')) {
            return null;
        }

        $window = Duty::window($user, $minutes);

        if ($window['open']) {
            return null;
        }

        return $window['next']
            ? __('dinein.window_later', ['time' => $window['next']->format('H:i'), 'minutes' => $minutes])
            : __('dinein.window_none', ['minutes' => $minutes]);
    }
}
