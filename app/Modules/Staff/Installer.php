<?php

namespace App\Modules\Staff;

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
    }
}
