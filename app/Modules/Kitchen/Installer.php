<?php

namespace App\Modules\Kitchen;

use App\Models\Restaurant;
use App\Models\RestaurantModule;
use App\Modules\DineIn\Models\Station;
use App\Modules\ModuleInstaller;

/** Bật Màn hình khu: tạo sẵn khu phục vụ mặc định (Bếp, Quầy nước) nếu quán chưa có. */
class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return ['sound' => Kitchen::SOUNDS[0]];
    }

    public function install(Restaurant $restaurant, RestaurantModule $state): void
    {
        parent::install($restaurant, $state);

        if (! Station::forRestaurant($restaurant)->exists()) {
            foreach (__('dinein.default_stations') as $index => $name) {
                Station::create(['restaurant_id' => $restaurant->getKey(), 'name' => $name, 'sort_order' => $index + 1]);
            }
        }
    }
}
