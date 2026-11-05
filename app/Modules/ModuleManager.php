<?php

namespace App\Modules;

use App\Models\Restaurant;
use App\Models\RestaurantModule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ModuleManager
{
    /** @var array<int, list<string>> trạng thái đã đọc trong request này */
    private array $loaded = [];

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return config('modules');
    }

    public function definition(string $module): array
    {
        return $this->definitions()[$module]
            ?? throw new InvalidArgumentException("Module không tồn tại: $module");
    }

    public function isCore(string $module): bool
    {
        return $this->definition($module)['core'] ?? false;
    }

    public function isAvailable(string $module): bool
    {
        return $this->definition($module)['available'] ?? true;
    }

    /** @return list<string> */
    public function enabledKeys(Restaurant $restaurant): array
    {
        return $this->loaded[$restaurant->getKey()] ??= Cache::remember(
            $this->cacheKey($restaurant),
            config('liresto.module_cache_seconds'),
            fn (): array => RestaurantModule::forRestaurant($restaurant)
                ->where('enabled', true)
                ->pluck('module')
                ->all(),
        );
    }

    public function isEnabled(Restaurant $restaurant, string $module): bool
    {
        return $this->isCore($module) || in_array($module, $this->enabledKeys($restaurant), true);
    }

    /** @return list<string> các module phụ thuộc còn thiếu */
    public function missingDependencies(Restaurant $restaurant, string $module): array
    {
        return array_values(array_filter(
            $this->definition($module)['depends'] ?? [],
            fn (string $dependency): bool => ! $this->isEnabled($restaurant, $dependency),
        ));
    }

    /** @return list<string> các module phụ thuộc vào $module, kể cả gián tiếp */
    public function dependentsOf(string $module): array
    {
        $found = [];

        foreach ($this->definitions() as $key => $definition) {
            if (in_array($module, $definition['depends'] ?? [], true)) {
                $found = [...$found, $key, ...$this->dependentsOf($key)];
            }
        }

        return array_values(array_unique($found));
    }

    /** @throws ModuleDependencyException */
    public function enable(Restaurant $restaurant, string $module): void
    {
        if ($this->isCore($module) || ! $this->isAvailable($module)) {
            throw new InvalidArgumentException("Module không bật được: $module");
        }

        if ($missing = $this->missingDependencies($restaurant, $module)) {
            throw new ModuleDependencyException(implode(', ', $missing));
        }

        DB::transaction(function () use ($restaurant, $module): void {
            $state = RestaurantModule::forRestaurant($restaurant)->where('module', $module)->first()
                ?? new RestaurantModule(['restaurant_id' => $restaurant->getKey(), 'module' => $module]);

            $state->enabled = true;
            $state->enabled_at ??= now();
            $state->save();

            if ($installer = $this->definition($module)['installer'] ?? null) {
                app($installer)->install($restaurant, $state);
            }
        });

        $this->forget($restaurant);
    }

    /**
     * Tắt module và các module phụ thuộc vào nó. Chỉ ẩn, không xoá dữ liệu.
     *
     * @return list<string> các module đã tắt
     */
    public function disable(Restaurant $restaurant, string $module): array
    {
        if ($this->isCore($module)) {
            throw new InvalidArgumentException("Không tắt được module lõi: $module");
        }

        $modules = [$module, ...$this->dependentsOf($module)];

        RestaurantModule::forRestaurant($restaurant)
            ->whereIn('module', $modules)
            ->update(['enabled' => false, 'enabled_at' => null]);

        $this->forget($restaurant);

        return $modules;
    }

    /** @return array<string, mixed> cài đặt đã lưu, thiếu thì lấy mặc định của module */
    public function settings(Restaurant $restaurant, string $module): array
    {
        $installer = $this->definition($module)['installer'] ?? null;

        return array_replace(
            $installer ? app($installer)->defaultSettings() : [],
            RestaurantModule::forRestaurant($restaurant)->where('module', $module)->value('settings') ?? [],
        );
    }

    /** @param array<string, mixed> $settings */
    public function updateSettings(Restaurant $restaurant, string $module, array $settings): void
    {
        $state = RestaurantModule::forRestaurant($restaurant)->where('module', $module)->firstOrFail();

        $state->update(['settings' => array_replace($state->settings ?? [], $settings)]);
    }

    public function forget(Restaurant $restaurant): void
    {
        unset($this->loaded[$restaurant->getKey()]);
        Cache::forget($this->cacheKey($restaurant));
    }

    private function cacheKey(Restaurant $restaurant): string
    {
        return "restaurant:{$restaurant->getKey()}:modules";
    }
}
