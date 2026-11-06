<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantModule;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $a;

    private Restaurant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Restaurant::factory()->create();
        $this->b = Restaurant::factory()->create();

        RestaurantModule::create(['restaurant_id' => $this->a->id, 'module' => 'menu']);
        RestaurantModule::create(['restaurant_id' => $this->b->id, 'module' => 'website']);
    }

    public function test_tenant_only_reads_its_own_rows(): void
    {
        $this->actingInTenant($this->a);

        $this->assertSame(['menu'], RestaurantModule::pluck('module')->all());
    }

    public function test_tenant_cannot_find_another_tenants_row_by_id(): void
    {
        $rowOfB = RestaurantModule::forRestaurant($this->b)->firstOrFail();

        $this->actingInTenant($this->a);

        $this->assertNull(RestaurantModule::find($rowOfB->id));
    }

    public function test_no_tenant_means_no_rows_rather_than_all_rows(): void
    {
        $this->assertSame(0, RestaurantModule::count());
    }

    public function test_new_rows_take_the_current_tenant(): void
    {
        $this->actingInTenant($this->a);

        $row = RestaurantModule::create(['module' => 'takeaway']);

        $this->assertSame($this->a->id, $row->restaurant_id);
    }

    public function test_writing_into_another_tenant_is_refused(): void
    {
        $this->actingInTenant($this->a);

        $this->expectException(LogicException::class);

        RestaurantModule::create(['restaurant_id' => $this->b->id, 'module' => 'takeaway']);
    }

    public function test_creating_without_a_tenant_is_refused(): void
    {
        app(TenantContext::class)->forget();

        $this->expectException(LogicException::class);

        RestaurantModule::create(['module' => 'takeaway']);
    }

    public function test_user_of_a_cannot_open_admin_of_b(): void
    {
        $owner = User::factory()->owner($this->a)->create();

        $this->actingAs($owner)->get("/{$this->b->slug}/admin")->assertForbidden();
        $this->actingAs($owner)->get("/{$this->a->slug}/admin")->assertOk();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get("/{$this->a->slug}/admin")->assertRedirect("/{$this->a->slug}/login");
    }
}
