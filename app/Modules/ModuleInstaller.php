<?php

namespace App\Modules;

use App\Models\Restaurant;
use App\Models\RestaurantModule;

/**
 * Chạy mỗi lần bật module, nên phải an toàn khi chạy lại: chỉ bổ sung cái còn
 * thiếu, không ghi đè cái quán đã chỉnh.
 */
abstract class ModuleInstaller
{
    /** @return array<string, mixed> */
    abstract public function defaultSettings(): array;

    public function install(Restaurant $restaurant, RestaurantModule $state): void
    {
        $state->settings = array_replace($this->defaultSettings(), $state->settings ?? []);
        $state->save();
    }
}
