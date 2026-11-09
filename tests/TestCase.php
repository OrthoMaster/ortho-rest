<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Position;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingInTenant(Restaurant $restaurant): void
    {
        app(TenantContext::class)->set($restaurant);
    }

    protected function enableModules(Restaurant $restaurant, string ...$modules): void
    {
        // Bật module là thao tác của super admin, không nằm trong quán nào.
        app(TenantContext::class)->forget();

        foreach ($modules as $module) {
            app(ModuleManager::class)->enable($restaurant, $module);
        }
    }

    /**
     * Người của quán không phải chủ quán: bật Nội bộ nếu chưa bật, rồi giao chức
     * vụ mặc định "manager" (Quản lý) hoặc "staff" (Nhân viên).
     */
    protected function member(Restaurant $restaurant, string $preset = 'staff', array $attributes = []): User
    {
        $context = app(TenantContext::class);
        $current = $context->get();

        if (! app(ModuleManager::class)->isEnabled($restaurant, 'staff')) {
            $this->enableModules($restaurant, 'staff');
        }

        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'role' => UserRole::Staff, ...$attributes]);
        $user->positions()->attach(Position::forRestaurant($restaurant)->where('preset', $preset)->firstOrFail());

        if ($current) {
            $context->set($current);
        }

        return $user;
    }
}
