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
            'min_notice_minutes' => 60,
            'max_advance_days' => 30,
            'turn_minutes' => 90,
            'slot_interval_minutes' => 30,
        ];
    }

    public function install(Restaurant $restaurant, RestaurantModule $state): void
    {
        parent::install($restaurant, $state);

        if (! Area::forRestaurant($restaurant)->exists()) {
            Area::create(['restaurant_id' => $restaurant->getKey(), 'name' => __('reservation.default_area'), 'capacity' => 40]);
        }
    }
}
