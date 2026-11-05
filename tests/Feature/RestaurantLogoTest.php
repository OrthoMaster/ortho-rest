<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantLogoTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->restaurant = Restaurant::factory()->create(['theme' => ['primary' => '#112233', 'secondary' => '#445566']]);
        $this->owner = User::factory()->owner($this->restaurant)->create();
    }

    private function png(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        ));
    }

    private function adminUrl(string $path = ''): string
    {
        return "/{$this->restaurant->slug}/admin$path";
    }

    public function test_owner_uploads_a_logo_and_colors_stay(): void
    {
        $this->actingAs($this->owner)
            ->post($this->adminUrl('/restaurant/logo'), ['logo' => $this->png()])
            ->assertSessionHasNoErrors();

        $theme = $this->restaurant->fresh()->theme;

        Storage::disk('public')->assertExists($theme['logo']);
        $this->assertSame('#112233', $theme['primary']);
    }

    public function test_super_admin_uploads_a_logo(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post("/super/restaurants/{$this->restaurant->id}/logo", ['logo' => $this->png()])
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists($this->restaurant->fresh()->theme['logo']);
    }

    public function test_replacing_deletes_the_old_file_and_removing_keeps_colors(): void
    {
        $this->actingAs($this->owner)->post($this->adminUrl('/restaurant/logo'), ['logo' => $this->png()]);
        $old = $this->restaurant->fresh()->theme['logo'];

        $this->actingAs($this->owner)->post($this->adminUrl('/restaurant/logo'), ['logo' => $this->png('moi.png')]);
        $new = $this->restaurant->fresh()->theme['logo'];

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);

        $this->actingAs($this->owner)->delete($this->adminUrl('/restaurant/logo'));

        Storage::disk('public')->assertMissing($new);
        $this->assertNull($this->restaurant->fresh()->logoUrl());
        $this->assertSame('#112233', $this->restaurant->fresh()->theme['primary']);
    }

    public function test_svg_other_files_and_big_files_are_refused(): void
    {
        $files = [
            UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('lon.png', 3000, 'image/png'),
        ];

        foreach ($files as $file) {
            $this->actingAs($this->owner)
                ->post($this->adminUrl('/restaurant/logo'), ['logo' => $file])
                ->assertSessionHasErrors('logo');
        }

        $this->assertNull($this->restaurant->fresh()->logoUrl());
    }

    public function test_text_file_named_png_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, '<?php echo "khong phai anh";');

        $file = new UploadedFile($path, 'x.png', 'image/png', null, true);

        $this->actingAs($this->owner)
            ->post($this->adminUrl('/restaurant/logo'), ['logo' => $file])
            ->assertSessionHasErrors('logo');
    }

    public function test_only_the_owner_of_this_restaurant_can_change_the_logo(): void
    {
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Manager]);
        $otherOwner = User::factory()->owner()->create();

        $this->actingAs($manager)->get($this->adminUrl('/restaurant'))->assertForbidden();
        $this->actingAs($manager)->post($this->adminUrl('/restaurant/logo'), ['logo' => $this->png()])->assertForbidden();
        $this->actingAs($otherOwner)->post($this->adminUrl('/restaurant/logo'), ['logo' => $this->png()])->assertForbidden();
        $this->actingAs($this->owner)->post("/super/restaurants/{$this->restaurant->id}/logo", ['logo' => $this->png()])->assertForbidden();

        $this->assertNull($this->restaurant->fresh()->logoUrl());
    }

    public function test_menu_link_is_for_the_owner_only(): void
    {
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Manager]);

        $this->actingAs($this->owner)->get($this->adminUrl())->assertSee(__('ui.restaurant.title'));
        $this->actingAs($manager)->get($this->adminUrl())->assertDontSee(__('ui.restaurant.title'));
    }

    public function test_placeholder_shows_until_a_logo_exists(): void
    {
        $this->actingAs($this->owner)->get($this->adminUrl())
            ->assertSee('<svg viewBox="0 0 24 24"', false);

        $this->actingAs($this->owner)->post($this->adminUrl('/restaurant/logo'), ['logo' => $this->png()]);

        $url = $this->restaurant->fresh()->logoUrl();

        $this->actingAs($this->owner)->get($this->adminUrl())
            ->assertSee($url)
            ->assertDontSee('<svg viewBox="0 0 24 24"', false);
    }

    public function test_restaurant_admin_shows_the_restaurant_name_not_liresto(): void
    {
        $this->actingAs($this->owner)->get($this->adminUrl())
            ->assertSee($this->restaurant->name)
            ->assertDontSee('>Liresto<', false)
            ->assertDontSee('liresto-mark.svg');
    }

    public function test_mobile_menu_button_is_in_the_shell(): void
    {
        $this->actingAs($this->owner)->get($this->adminUrl())
            ->assertSee('class="nav-toggle"', false)
            ->assertSee('aria-controls="sidebar-panel"', false);
    }

    public function test_platform_pages_show_the_liresto_logo(): void
    {
        $this->get('/login')->assertSee('images/liresto-mark.svg');
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super/restaurants')
            ->assertSee('images/liresto-mark.svg');
    }
}
