<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Tenancy\RestaurantTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RestaurantThemeTest extends TestCase
{
    use RefreshDatabase;

    private const STYLE = '<style>:root';

    private function themed(): Restaurant
    {
        $restaurant = Restaurant::factory()->create([
            'theme' => ['primary' => '#8c2f1b', 'secondary' => '#f2c14e'],
        ]);
        $this->enableModules($restaurant, 'website');

        return $restaurant;
    }

    public function test_public_site_and_admin_use_the_restaurant_colors(): void
    {
        $restaurant = $this->themed();
        $owner = User::factory()->owner($restaurant)->create();

        $this->get("/{$restaurant->slug}")->assertSee('--primary: #8c2f1b;', false);
        $this->actingAs($owner)->get("/{$restaurant->slug}/admin")->assertSee('--secondary: #f2c14e;', false);
    }

    /** Chủ quán tự đổi màu ở Cài đặt chung (khung Logo và màu), có nút về màu mặc định; logo giữ nguyên. */
    public function test_owner_changes_colors_and_resets_to_default(): void
    {
        $restaurant = Restaurant::factory()->create(['theme' => ['logo' => 'logos/a.png']]);
        $owner = User::factory()->owner($restaurant)->create();
        $settings = "/{$restaurant->slug}/admin/restaurant";

        $this->actingAs($owner)->get($settings)->assertOk()
            ->assertSee(__('ui.super.theme'))
            ->assertSee('value="'.RestaurantTheme::PRIMARY.'"', false)
            ->assertDontSee(__('ui.super.theme_reset_action'))
            ->assertDontSee('Liresto');

        $this->put("$settings/theme", ['primary' => '#8C2F1B', 'secondary' => '#f2c14e'])->assertSessionHasNoErrors();
        $this->assertSame(['logo' => 'logos/a.png', 'primary' => '#8c2f1b', 'secondary' => '#f2c14e'], $restaurant->fresh()->theme);
        $this->get($settings)->assertSee('--primary: #8c2f1b;', false)->assertSee(__('ui.super.theme_reset_action'));
        $this->put("$settings/theme", ['primary' => 'red', 'secondary' => '#f2c14e'])->assertSessionHasErrors('primary');

        $this->delete("$settings/theme")->assertSessionHas('status', __('ui.super.theme_reset'));
        $this->assertSame(['logo' => 'logos/a.png'], $restaurant->fresh()->theme);

        $this->actingAs($this->member($restaurant))->put("$settings/theme", ['primary' => '#000000', 'secondary' => '#ffffff'])->assertForbidden();
    }

    public function test_custom_domain_uses_the_restaurant_colors(): void
    {
        $restaurant = $this->themed();
        $restaurant->domains()->create(['domain' => 'nhahang.vn', 'verified_at' => now()]);

        $this->get('http://nhahang.vn/')->assertSee('--primary: #8c2f1b;', false);
    }

    public function test_platform_pages_keep_liresto_colors(): void
    {
        $this->themed();

        $this->get('/')->assertDontSee(self::STYLE, false);
        $this->get('/login')->assertDontSee(self::STYLE, false);
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super/restaurants')
            ->assertDontSee(self::STYLE, false);
    }

    public function test_restaurant_without_colors_keeps_liresto_colors(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->enableModules($restaurant, 'website');

        $this->get("/{$restaurant->slug}")->assertOk()->assertDontSee(self::STYLE, false);
    }

    public function test_browser_title_starts_with_the_restaurant_name(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Phở & Cơm']);
        $this->enableModules($restaurant, 'website', 'menu');
        $owner = User::factory()->owner($restaurant)->create();

        $this->actingAs($owner)->get("/{$restaurant->slug}/admin/menu")
            ->assertSee('<title>Phở &amp; Cơm - Menu</title>', false);

        $this->get("/{$restaurant->slug}")->assertSee('<title>Phở &amp; Cơm</title>', false);
    }

    public function test_platform_pages_keep_their_own_title(): void
    {
        $this->get('/login')->assertSee('<title>Đăng nhập</title>', false);
    }

    public function test_default_constants_match_the_stylesheet(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('--primary: '.RestaurantTheme::PRIMARY, $css);
        $this->assertStringContainsString('--secondary: '.RestaurantTheme::SECONDARY, $css);
        $this->assertStringContainsString('--on-secondary: '.RestaurantTheme::textOn(RestaurantTheme::SECONDARY), $css);
    }

    #[TestWith(['#8c2f1b'])]
    #[TestWith(['#a8d0f0'])]
    #[TestWith(['#fbe7a8'])]
    #[TestWith(['#ffffff'])]
    #[TestWith(['#000000'])]
    #[TestWith(['#7f7f7f'])]
    public function test_text_stays_readable_on_any_color(string $color): void
    {
        $text = RestaurantTheme::textOn($color);

        $this->assertGreaterThanOrEqual(3.0, RestaurantTheme::contrast($color, $text));
        $this->assertGreaterThanOrEqual(4.5, RestaurantTheme::contrast(RestaurantTheme::readableOnWhite($color), '#ffffff'));
    }

    public function test_dark_color_gets_light_text_and_pastel_gets_dark_text(): void
    {
        $this->assertSame('#ffffff', RestaurantTheme::textOn('#8c2f1b'));
        $this->assertSame('#1f3a52', RestaurantTheme::textOn('#a8d0f0'));
    }
}
