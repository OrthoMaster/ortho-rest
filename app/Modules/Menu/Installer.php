<?php

namespace App\Modules\Menu;

use App\Models\Restaurant;
use App\Models\RestaurantModule;
use App\Modules\Menu\Models\MenuTag;
use App\Modules\ModuleInstaller;

class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return [];
    }

    public function install(Restaurant $restaurant, RestaurantModule $state): void
    {
        parent::install($restaurant, $state);

        if (MenuTag::forRestaurant($restaurant)->exists()) {
            return;
        }

        foreach (__('menu.default_tags') as $name) {
            MenuTag::create(['restaurant_id' => $restaurant->getKey(), 'name' => $name]);
        }
    }
}
