<?php

namespace App\Modules\Reservation;

use App\Models\Restaurant;
use App\Models\RestaurantModule;
use App\Modules\ModuleInstaller;
use App\Modules\Reservation\Models\Area;

class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return [
            'turn_minutes' => 90,
            // Vai trò được nhận, từ chối đặt bàn ngoài chủ quán. Sẽ do phần vai trò và tài khoản quản lý.
            'online_enabled' => true,
            'min_notice_days' => 0,
            'max_advance_days' => 30,
            'auto_confirm' => false,
            // null: chưa đặt riêng, dùng tổng chỗ ngồi của các khu vực.
            'online_seats' => null,
            'online_seats_total' => null,
        ];
    }

    public function install(Restaurant $restaurant, RestaurantModule $state): void
    {
        parent::install($restaurant, $state);

        if (! Area::forRestaurant($restaurant)->exists()) {
            Area::create(['restaurant_id' => $restaurant->getKey(), 'name' => __('reservation.default_area'), 'code' => 'A', 'table_count' => 10, 'capacity' => 40, 'sort_order' => 1]);
        }
    }
}
