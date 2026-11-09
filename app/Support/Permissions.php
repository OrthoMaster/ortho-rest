<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Position;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\ModuleManager;

/**
 * Quyền khai trong config/permissions.php, chia theo module. Chủ quán luôn có
 * mọi quyền. Người khác chỉ có quyền khi quán bật module Nội bộ, tài khoản
 * đang hoạt động, quyền thuộc module đang bật và nằm trong một chức vụ của họ.
 */
class Permissions
{
    public const PRESETS = ['manager', 'staff'];

    /** @return array<string, array<string, list<string>>> module => quyền => chức vụ mặc định */
    public static function catalog(): array
    {
        return config('permissions');
    }

    /** Tên đọc được của quyền. Khoá có dấu chấm nên không gọi __('permissions.labels.'.$key) được. */
    public static function label(string $key): string
    {
        return __('permissions.labels')[$key] ?? $key;
    }

    public static function exists(string $key): bool
    {
        return self::moduleOf($key) !== null;
    }

    public static function moduleOf(string $key): ?string
    {
        foreach (self::catalog() as $module => $keys) {
            if (array_key_exists($key, $keys)) {
                return $module;
            }
        }

        return null;
    }

    /** Các nhóm quyền của module đang bật ở quán này, theo thứ tự khai báo. */
    public static function groupsFor(Restaurant $restaurant): array
    {
        return array_filter(self::catalog(), fn (string $module) => self::moduleOn($restaurant, $module), ARRAY_FILTER_USE_KEY);
    }

    /** @return list<string> */
    public static function defaults(string $preset, string $module): array
    {
        return array_keys(array_filter(self::catalog()[$module] ?? [], fn (array $presets) => in_array($preset, $presets, true)));
    }

    public static function allows(User $user, string $key): bool
    {
        $restaurant = $user->restaurant;

        if ($restaurant === null || ! self::exists($key)) {
            return false;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        return $user->is_active
            && self::moduleOn($restaurant, 'staff')
            && self::moduleOn($restaurant, self::moduleOf($key))
            && in_array($key, $user->permissionKeys(), true);
    }

    /** Tạo hai chức vụ mặc định Quản lý, Nhân viên nếu quán chưa có. */
    public static function ensurePresets(Restaurant $restaurant): void
    {
        foreach (self::PRESETS as $order => $preset) {
            if (Position::forRestaurant($restaurant)->where('preset', $preset)->exists()) {
                continue;
            }

            $permissions = [];

            foreach (array_keys(self::catalog()) as $module) {
                if (self::moduleOn($restaurant, $module) || $module === 'staff') {
                    $permissions = [...$permissions, ...self::defaults($preset, $module)];
                }
            }

            $position = new Position(['name' => __('permissions.presets.'.$preset), 'preset' => $preset, 'permissions' => $permissions, 'sort_order' => $order + 1]);
            $position->restaurant_id = $restaurant->getKey();
            $position->save();
        }
    }

    /** Bật một module: giao sẵn quyền mặc định của module đó cho chức vụ Quản lý, Nhân viên. */
    public static function grantModuleDefaults(Restaurant $restaurant, string $module): void
    {
        foreach (Position::forRestaurant($restaurant)->whereNotNull('preset')->get() as $position) {
            $position->permissions = array_values(array_unique([...$position->permissions, ...self::defaults($position->preset, $module)]));
            $position->save();
        }
    }

    private static function moduleOn(Restaurant $restaurant, string $module): bool
    {
        return $module === 'core' || app(ModuleManager::class)->isEnabled($restaurant, $module);
    }
}
