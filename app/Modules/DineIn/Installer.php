<?php

namespace App\Modules\DineIn;

use App\Models\Restaurant;
use App\Models\RestaurantModule;
use App\Modules\DineIn\Models\Station;
use App\Modules\ModuleInstaller;
use App\Modules\Reservation\Models\Area;

class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return [];
    }

    public function install(Restaurant $restaurant, RestaurantModule $state): void
    {
        parent::install($restaurant, $state);

        // Bàn lấy theo mã khu vực ở Sức chứa, nên cần ít nhất một khu vực.
        if (! Area::forRestaurant($restaurant)->exists()) {
            Area::create(['restaurant_id' => $restaurant->getKey(), 'name' => __('reservation.default_area'), 'code' => 'A', 'table_count' => 10, 'capacity' => 40, 'sort_order' => 1]);
        }

        if (! Station::forRestaurant($restaurant)->exists()) {
            foreach (__('dinein.default_stations') as $index => $name) {
                Station::create(['restaurant_id' => $restaurant->getKey(), 'name' => $name, 'sort_order' => $index + 1]);
            }
        }
    }
}
