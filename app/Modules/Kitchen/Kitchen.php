<?php

namespace App\Modules\Kitchen;

use App\Modules\Menu\Models\MenuItem;
use App\Modules\ModuleManager;

/**
 * Món đi qua màn hình khu khi quán bật Màn hình khu và món đã chọn khu ở
 * Cài đặt chung → Khu vực. Món không có khu (hoặc tắt module) không cần ai bấm Xong.
 */
final class Kitchen
{
    /** Tiếng chuông báo món (chủ quán chọn ở Cài đặt chung). */
    public const SOUNDS = ['chime', 'bell', 'alarm'];

    public static function sound(): string
    {
        $sound = tenant() && has_module('kitchen') ? (app(ModuleManager::class)->settings(tenant(), 'kitchen')['sound'] ?? null) : null;

        return in_array($sound, self::SOUNDS, true) ? $sound : self::SOUNDS[0];
    }

    public static function stationFor(MenuItem $item): ?int
    {
        return has_module('kitchen') ? $item->dinein_station_id : null;
    }
}
