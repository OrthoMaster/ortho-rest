<?php

namespace Tests\Feature;

use App\Enums\RestaurantStatus;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_only_super_admin_gets_in(): void
    {
        $this->get('/super/restaurants')->assertRedirect('/login');
        $this->actingAs(User::factory()->owner()->create())->get('/super/restaurants')->assertForbidden();
        $this->actingAs($this->admin)->get('/super/restaurants')->assertOk();
    }

    public function test_create_restaurant_with_owner_and_generated_slug(): void
    {
        $this->actingAs($this->admin)->post('/super/restaurants', [
            'name' => 'Phở Đầu Bò',
            'owner_name' => 'Chị Hoa',
            'owner_email' => 'hoa@pho.vn',
            'owner_password' => 'mat-khau-dai',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $restaurant = Restaurant::where('slug', 'pho-dau-bo')->firstOrFail();
        $owner = $restaurant->owner();

        $this->assertSame('hoa@pho.vn', $owner->email);
        $this->assertSame(UserRole::Owner, $owner->role);
    }

    public function test_reserved_and_invalid_slugs_are_rejected(): void
    {
        $restaurant = Restaurant::factory()->create();

        foreach (['super', 'login', 'en', 'Co Dau', '-x', 'a'] as $slug) {
            $this->actingAs($this->admin)
                ->put("/super/restaurants/{$restaurant->id}", ['name' => 'Quán', 'slug' => $slug])
                ->assertSessionHasErrors('slug');
        }
    }

    public function test_show_page_renders_all_sections(): void
    {
        $restaurant = Restaurant::factory()->create();
        User::factory()->owner($restaurant)->create();

        $this->actingAs($this->admin)->get("/super/restaurants/{$restaurant->id}")
            ->assertOk()
            ->assertSee(__('modules.takeaway'))
            ->assertSee(__('ui.super.domains'));
    }

    public function test_domain_lifecycle(): void
    {
        $restaurant = Restaurant::factory()->create();
        $base = "/super/restaurants/{$restaurant->id}/domains";

        $this->actingAs($this->admin)->post($base, ['domain' => 'https://NhaHang.vn/menu'])->assertSessionHasNoErrors();

        $domain = $restaurant->domains()->sole();
        $this->assertSame('nhahang.vn', $domain->domain);
        $this->assertNull($domain->verified_at);

        $this->actingAs($this->admin)->post("$base/{$domain->id}/verify");
        $this->assertNotNull($domain->fresh()->verified_at);
        $this->assertSame('nhahang.vn', $restaurant->primaryDomain());

        $this->actingAs($this->admin)->delete("$base/{$domain->id}");
        $this->assertSame(0, $restaurant->domains()->count());
    }

    public function test_main_domain_and_bad_hosts_are_refused(): void
    {
        $restaurant = Restaurant::factory()->create();

        foreach (['localhost', '127.0.0.1', 'khong co dau cham'] as $domain) {
            $this->actingAs($this->admin)->post("/super/restaurants/{$restaurant->id}/domains", ['domain' => $domain])
                ->assertSessionHasErrors('domain');
        }
    }

    public function test_domain_of_another_restaurant_cannot_be_touched_through_this_one(): void
    {
        $a = Restaurant::factory()->create();
        $b = Restaurant::factory()->create();
        $domain = $b->domains()->create(['domain' => 'b.vn']);

        $this->actingAs($this->admin)->delete("/super/restaurants/{$a->id}/domains/{$domain->id}")->assertNotFound();

        $this->assertNotNull($domain->fresh());
    }

    public function test_set_and_reset_restaurant_colors(): void
    {
        $restaurant = Restaurant::factory()->create(['theme' => ['font' => 'giu-nguyen']]);
        $url = "/super/restaurants/{$restaurant->id}/theme";

        $this->actingAs($this->admin)->put($url, ['primary' => '#AABBCC', 'secondary' => '#ffeedd'])->assertSessionHasNoErrors();
        $this->assertSame(
            ['font' => 'giu-nguyen', 'primary' => '#aabbcc', 'secondary' => '#ffeedd'],
            $restaurant->fresh()->theme,
        );

        $this->actingAs($this->admin)->delete($url);
        $this->assertSame(['font' => 'giu-nguyen'], $restaurant->fresh()->theme);
    }

    public function test_colors_that_are_not_hex_are_refused(): void
    {
        $restaurant = Restaurant::factory()->create();

        foreach (['red', '#abc', '#aabbcc;} body{display:none', '</style>'] as $color) {
            $this->actingAs($this->admin)
                ->put("/super/restaurants/{$restaurant->id}/theme", ['primary' => $color, 'secondary' => '#ffeedd'])
                ->assertSessionHasErrors('primary');
        }
    }

    public function test_lock_and_unlock(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($this->admin)->post("/super/restaurants/{$restaurant->id}/lock");
        $this->assertSame(RestaurantStatus::Locked, $restaurant->fresh()->status);

        $this->actingAs($this->admin)->post("/super/restaurants/{$restaurant->id}/unlock");
        $this->assertSame(RestaurantStatus::Active, $restaurant->fresh()->status);
    }

    public function test_toggle_module_and_dependency_message(): void
    {
        $restaurant = Restaurant::factory()->create();
        $url = "/super/restaurants/{$restaurant->id}/modules";

        $this->actingAs($this->admin)->put("$url/takeaway", ['enabled' => 1])->assertSessionHas('error');

        $this->actingAs($this->admin)->put("$url/menu", ['enabled' => 1])->assertSessionHas('status');
        $this->actingAs($this->admin)->put("$url/takeaway", ['enabled' => 1])->assertSessionHas('status');
        $this->assertTrue($restaurant->hasModule('takeaway'));

        $this->actingAs($this->admin)->put("$url/menu", ['enabled' => 0])->assertSessionHas('status');
        $this->assertFalse($restaurant->hasModule('takeaway'));
    }

    public function test_core_phase2_and_unknown_modules_cannot_be_toggled(): void
    {
        $restaurant = Restaurant::factory()->create();
        $url = "/super/restaurants/{$restaurant->id}/modules";

        $this->actingAs($this->admin)->put("$url/core", ['enabled' => 0])->assertUnprocessable();
        $this->actingAs($this->admin)->put("$url/staff", ['enabled' => 1])->assertUnprocessable();
        $this->actingAs($this->admin)->put("$url/khong-co", ['enabled' => 1])->assertNotFound();
    }

    public function test_impersonate_and_leave(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->owner($restaurant)->create();

        $this->actingAs($this->admin)->post("/super/restaurants/{$restaurant->id}/impersonate")
            ->assertRedirect("/{$restaurant->slug}/admin");
        $this->assertSame($owner->id, Auth::id());

        $this->get("/{$restaurant->slug}/admin")->assertOk()->assertSee(__('ui.impersonation.leave'));

        $this->post('/impersonate/leave')->assertRedirect("/super/restaurants/{$restaurant->id}");
        $this->assertSame($this->admin->id, Auth::id());
    }

    public function test_impersonating_works_on_a_locked_restaurant(): void
    {
        $restaurant = Restaurant::factory()->locked()->create();
        User::factory()->owner($restaurant)->create();

        $this->actingAs($this->admin)->post("/super/restaurants/{$restaurant->id}/impersonate");

        $this->get("/{$restaurant->slug}/admin")->assertOk();
    }

    public function test_ordinary_user_cannot_use_leave_to_gain_anything(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/impersonate/leave')->assertForbidden();
        $this->assertSame($owner->id, Auth::id());
    }

    public function test_owner_cannot_start_impersonating(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post("/super/restaurants/{$restaurant->id}/impersonate")->assertForbidden();
    }
}
