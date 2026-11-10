<?php

namespace App\Modules\Staff;

use App\Models\Department;
use App\Models\Restaurant;
use App\Models\RestaurantModule;
use App\Modules\ModuleInstaller;
use App\Support\Permissions;

class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return [];
    }

    public function install(Restaurant $restaurant, RestaurantModule $state): void
    {
        parent::install($restaurant, $state);

        Permissions::ensurePresets($restaurant);

        // Hai bộ phận có sẵn để xếp lịch; quán thêm Bar, Thu ngân... khi cần.
        if (! Department::forRestaurant($restaurant)->exists()) {
            foreach (__('staff.default_departments') as $index => $name) {
                Department::create(['restaurant_id' => $restaurant->getKey(), 'name' => $name, 'tone' => $index + 1, 'sort_order' => $index + 1]);
            }
        }
    }
}
