<?php

namespace App\Modules\Kitchen;

use App\Modules\Menu\Models\MenuItem;

/**
 * Món đi qua màn hình khu khi quán bật Màn hình khu và món đã chọn khu ở
 * Cài đặt chung → Khu vực. Món không có khu (hoặc tắt module) không cần ai bấm Xong.
 */
final class Kitchen
{
    public static function stationFor(MenuItem $item): ?int
    {
        return has_module('kitchen') ? $item->dinein_station_id : null;
    }
}
