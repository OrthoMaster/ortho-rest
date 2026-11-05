<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(?string $domain = null, bool $verified = true): Restaurant
    {
        $restaurant = Restaurant::factory()->create();
        $this->enableModules($restaurant, 'website');

        if ($domain) {
            $restaurant->domains()->create(['domain' => $domain, 'verified_at' => $verified ? now() : null]);
        }

        return $restaurant;
    }

    public function test_site_opens_by_slug(): void
    {
        $restaurant = $this->restaurant();

        $this->get("/{$restaurant->slug}")->assertOk()->assertSee($restaurant->name);
    }

    public function test_site_opens_by_verified_domain(): void
    {
        $restaurant = $this->restaurant('nhahang.vn');

        $this->get('http://nhahang.vn/')->assertOk()->assertSee($restaurant->name);
    }

    public function test_unknown_slug_and_unknown_domain_are_404(): void
    {
        $this->get('/khong-co-quan-nay')->assertNotFound()->assertSee(__('ui.errors.404_title'));
        $this->get('http://la.example/')->assertNotFound();
    }

    public function test_unverified_domain_is_404(): void
    {
        $this->restaurant('chua-xac-minh.vn', verified: false);

        $this->get('http://chua-xac-minh.vn/')->assertNotFound();
    }

    public function test_slug_redirects_301_to_the_custom_domain_and_keeps_the_query(): void
    {
        $restaurant = $this->restaurant('nhahang.vn');

        $this->get("/{$restaurant->slug}?utm=zalo")
            ->assertStatus(301)
            ->assertRedirect('http://nhahang.vn/?utm=zalo');
    }

    public function test_unverified_domain_does_not_redirect(): void
    {
        $restaurant = $this->restaurant('chua-xac-minh.vn', verified: false);

        $this->get("/{$restaurant->slug}")->assertOk();
    }

    public function test_admin_stays_on_the_main_domain_even_with_a_custom_domain(): void
    {
        $restaurant = $this->restaurant('nhahang.vn');
        $owner = User::factory()->owner($restaurant)->create();

        $this->actingAs($owner)->get("/{$restaurant->slug}/admin")->assertOk();
        $this->actingAs($owner)->get('http://nhahang.vn/admin')->assertNotFound();
        $this->actingAs($owner)->get("http://nhahang.vn/{$restaurant->slug}/admin")->assertNotFound();
        $this->get('http://nhahang.vn/login')->assertNotFound();
        $this->get('http://nhahang.vn/super')->assertNotFound();
    }

    public function test_tenant_route_uses_the_right_url_style(): void
    {
        $bySlug = $this->restaurant();
        $byDomain = $this->restaurant('nhahang.vn');

        $this->assertSame("http://localhost/{$bySlug->slug}", tenant_route('public.home', restaurant: $bySlug));
        $this->assertSame('http://nhahang.vn', tenant_route('public.home', restaurant: $byDomain));
        $this->assertSame("http://localhost/{$byDomain->slug}/admin", tenant_route('admin.dashboard', restaurant: $byDomain));
    }

    public function test_locked_restaurant_shows_the_locked_page(): void
    {
        $restaurant = Restaurant::factory()->locked()->create();
        $this->enableModules($restaurant, 'website');

        $this->get("/{$restaurant->slug}")->assertForbidden()->assertSee(__('ui.public.locked_title'));
    }

    public function test_caddy_ask_endpoint_only_accepts_verified_domains(): void
    {
        $this->restaurant('nhahang.vn');
        $this->restaurant('chua-xac-minh.vn', verified: false);

        $this->get('/internal/caddy/ask?domain=nhahang.vn')->assertOk();
        $this->get('/internal/caddy/ask?domain=NHAHANG.vn')->assertOk();
        $this->get('/internal/caddy/ask?domain=chua-xac-minh.vn')->assertNotFound();
        $this->get('/internal/caddy/ask?domain=la.example')->assertNotFound();
        $this->get('/internal/caddy/ask')->assertNotFound();
    }

    public function test_platform_home(): void
    {
        $this->get('/')->assertOk()->assertSee(__('ui.platform.login'));
    }
}
