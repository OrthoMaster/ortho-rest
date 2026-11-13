<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Chủ quán chọn một trong ba tiếng chuông báo món ở Cài đặt chung, chỉ khi bật Màn hình khu. */
class AlertSoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_picks_the_sound_and_every_page_uses_it(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->enableModules($restaurant, 'menu', 'kitchen');
        $owner = User::factory()->owner($restaurant)->create();
        $settings = "/{$restaurant->slug}/admin/restaurant";

        $this->actingAs($owner)->get($settings)->assertOk()
            ->assertSee(__('kitchen.sound_title'))
            ->assertSee('data-sound-preview="bell"', false)
            ->assertSee('data-alert-sound="chime"', false);

        $this->put("$settings/sound", ['sound' => 'alarm'])->assertSessionHasNoErrors();
        $this->assertSame('alarm', app(ModuleManager::class)->settings($restaurant, 'kitchen')['sound']);
        $this->get("/{$restaurant->slug}/admin")->assertSee('data-alert-sound="alarm"', false);

        $this->put("$settings/sound", ['sound' => 'siren'])->assertSessionHasErrors('sound');
    }

    public function test_hidden_without_the_kitchen_module(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->enableModules($restaurant, 'menu');
        $owner = User::factory()->owner($restaurant)->create();

        $this->actingAs($owner)->get("/{$restaurant->slug}/admin/restaurant")->assertOk()
            ->assertDontSee(__('kitchen.sound_title'))
            ->assertDontSee('data-alert-sound', false);
        $this->put("/{$restaurant->slug}/admin/restaurant/sound", ['sound' => 'bell'])->assertNotFound();
    }

    public function test_staff_cannot_change_the_sound(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->enableModules($restaurant, 'menu', 'kitchen');
        $staff = $this->member($restaurant);

        $this->actingAs($staff)->put("/{$restaurant->slug}/admin/restaurant/sound", ['sound' => 'bell'])->assertForbidden();
    }
}
