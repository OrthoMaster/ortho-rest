<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\RestaurantModule;
use App\Models\User;
use App\Modules\ModuleDependencyException;
use App\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    use RefreshDatabase;

    private function modules(): ModuleManager
    {
        return app(ModuleManager::class);
    }

    public function test_core_is_always_on_and_others_start_off(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->assertTrue($restaurant->hasModule('core'));
        $this->assertFalse($restaurant->hasModule('menu'));
    }

    public function test_enabling_needs_dependencies(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->expectException(ModuleDependencyException::class);

        $this->modules()->enable($restaurant, 'takeaway');
    }

    public function test_disabling_cascades_to_dependents_and_keeps_data(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->enableModules($restaurant, 'menu', 'takeaway');

        $disabled = $this->modules()->disable($restaurant, 'menu');

        $this->assertSame(['menu', 'takeaway'], $disabled);
        $this->assertFalse($restaurant->hasModule('takeaway'));
        $this->assertSame(2, RestaurantModule::forRestaurant($restaurant)->count());
    }

    public function test_installer_can_run_again_without_overwriting_settings(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->enableModules($restaurant, 'reservation');

        $state = RestaurantModule::forRestaurant($restaurant)->where('module', 'reservation')->first();
        $state->update(['settings' => [...$state->settings, 'turn_minutes' => 120]]);

        $this->enableModules($restaurant, 'reservation');

        $settings = $state->fresh()->settings;
        $this->assertSame(120, $settings['turn_minutes']);
        $this->assertSame(30, $settings['max_advance_days']);
        $this->assertSame(1, RestaurantModule::forRestaurant($restaurant)->count());
    }

    public function test_cached_state_is_refreshed_after_a_change(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->assertFalse($restaurant->hasModule('menu'));
        $this->enableModules($restaurant, 'menu');
        $this->assertTrue($restaurant->hasModule('menu'));
    }

    public function test_module_route_is_404_when_off(): void
    {
        $owner = User::factory()->owner()->create();
        $restaurant = $owner->restaurant;

        $this->actingAs($owner)->get("/{$restaurant->slug}/admin/menu")->assertNotFound();

        $this->enableModules($restaurant, 'menu');

        $this->actingAs($owner)->get("/{$restaurant->slug}/admin/menu")->assertOk();
    }

    public function test_public_site_is_404_when_website_is_off(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->get("/{$restaurant->slug}")->assertNotFound();
    }

    public function test_admin_menu_hides_modules_that_are_off(): void
    {
        $owner = User::factory()->owner()->create();
        $this->enableModules($owner->restaurant, 'reservation');

        $this->actingAs($owner)->get("/{$owner->restaurant->slug}/admin")
            ->assertSee(route('tenant.admin.reservation.index', ['resto' => $owner->restaurant->slug]))
            ->assertDontSee(route('tenant.admin.menu.index', ['resto' => $owner->restaurant->slug]));
    }

    public function test_role_limits_module_pages_and_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->enableModules($restaurant, 'menu', 'reservation');
        $staff = User::factory()->create(['restaurant_id' => $restaurant->id, 'role' => UserRole::Staff]);

        $this->actingAs($staff)->get("/{$restaurant->slug}/admin/menu")->assertForbidden();
        $this->actingAs($staff)->get("/{$restaurant->slug}/admin/reservations")->assertOk();
        $this->actingAs($staff)->get("/{$restaurant->slug}/admin")
            ->assertDontSee(route('tenant.admin.menu.index', ['resto' => $restaurant->slug]));
    }
}
