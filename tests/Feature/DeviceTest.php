<?php

namespace Tests\Feature;

use App\Auth\DeviceGuard;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private User $owner;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->slug = $this->owner->restaurant->slug;
    }

    private function login(string $agent, ?string $deviceCookie = null)
    {
        $request = $this->withHeader('User-Agent', $agent);

        if ($deviceCookie !== null) {
            $request = $request->withCookie(DeviceGuard::COOKIE, $deviceCookie);
        }

        return $request->post("/{$this->slug}/login", ['login' => $this->owner->email, 'password' => 'password']);
    }

    private function logout(): void
    {
        $this->post('/logout');
        $this->flushSession();
    }

    public function test_login_registers_the_device_with_a_readable_name(): void
    {
        $response = $this->login(self::IPHONE);

        $response->assertRedirect("/{$this->slug}/admin")->assertCookie(DeviceGuard::COOKIE);

        $device = $this->owner->devices()->sole();
        $this->assertSame('Safari trên iPhone', $device->name);

        $this->get("/{$this->slug}/admin/profile")->assertSee('Safari trên iPhone')->assertSee(__('ui.devices.current'));
    }

    public function test_same_browser_logging_in_again_reuses_the_device(): void
    {
        $cookie = $this->login(self::IPHONE)->getCookie(DeviceGuard::COOKIE)->getValue();
        $this->logout();

        $this->login(self::IPHONE, $cookie)->assertRedirect("/{$this->slug}/admin");

        $this->assertSame(1, $this->owner->devices()->count());
    }

    public function test_removed_device_is_logged_out_on_its_next_request(): void
    {
        $this->login(self::IPHONE);
        $phone = $this->owner->devices()->sole();
        $this->logout();

        $this->login(self::WINDOWS);
        $this->delete("/{$this->slug}/admin/profile/devices/{$phone->id}")->assertSessionHas('status');
        $this->assertNull($phone->fresh());

        // Phiên của chiếc điện thoại: session còn trỏ tới thiết bị đã xoá.
        $this->withSession([DeviceGuard::SESSION_KEY => $phone->id, DeviceGuard::SESSION_USER_KEY => $this->owner->id])
            ->get("/{$this->slug}/admin")
            ->assertRedirect("/{$this->slug}/login")
            ->assertSessionHas('error', __('ui.devices.revoked'));
        $this->assertGuest();
    }

    public function test_current_device_cannot_be_removed_from_the_list(): void
    {
        $this->login(self::IPHONE);
        $device = $this->owner->devices()->sole();

        $this->delete("/{$this->slug}/admin/profile/devices/{$device->id}")->assertSessionHas('error');
        $this->assertNotNull($device->fresh());
    }

    public function test_lock_refuses_new_devices_but_keeps_known_ones(): void
    {
        $cookie = $this->login(self::IPHONE)->getCookie(DeviceGuard::COOKIE)->getValue();
        $this->put("/{$this->slug}/admin/profile/devices/lock", ['devices_locked' => 1])->assertSessionHas('status');
        $this->logout();

        $this->login(self::WINDOWS)->assertSessionHasErrors(['login' => __('ui.devices.blocked')]);
        $this->assertGuest();
        $this->assertSame(1, $this->owner->devices()->count());

        $this->flushSession();
        $this->login(self::IPHONE, $cookie)->assertRedirect("/{$this->slug}/admin");
        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_locked_account_already_signed_in_elsewhere_is_stopped(): void
    {
        $this->owner->forceFill(['devices_locked' => true])->save();

        $this->actingAs($this->owner)->get("/{$this->slug}/admin")->assertRedirect("/{$this->slug}/login");
        $this->assertGuest();
    }

    public function test_a_user_cannot_remove_someone_elses_device(): void
    {
        $other = User::factory()->owner()->create();
        $device = $other->devices()->create(['token_hash' => str_repeat('a', 64), 'name' => 'Máy khác']);

        $this->actingAs($this->owner)->delete("/{$this->slug}/admin/profile/devices/{$device->id}")->assertNotFound();
        $this->assertNotNull($device->fresh());
    }

    public function test_super_admin_can_unlock_the_owner(): void
    {
        $this->owner->forceFill(['devices_locked' => true])->save();
        $restaurant = $this->owner->restaurant;

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get("/super/restaurants/{$restaurant->id}")
            ->assertSee(__('ui.super.devices_unlock'));

        $this->post("/super/restaurants/{$restaurant->id}/devices/unlock")->assertSessionHas('status');

        $this->assertFalse($this->owner->fresh()->devices_locked);
    }

    public function test_impersonation_does_not_add_a_device_to_the_owner(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->owner($restaurant)->create();
        $owner->forceFill(['devices_locked' => true])->save();

        $this->actingAs(User::factory()->superAdmin()->create())->post("/super/restaurants/{$restaurant->id}/impersonate");

        $this->get("/{$restaurant->slug}/admin")->assertOk();
        $this->assertSame(0, $owner->devices()->count());
    }
}
