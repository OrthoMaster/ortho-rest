<?php

namespace Tests;

use App\Models\Restaurant;
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
        foreach ($modules as $module) {
            app(ModuleManager::class)->enable($restaurant, $module);
        }
    }
}
