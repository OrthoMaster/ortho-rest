<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_logs_in_to_their_restaurant(): void
    {
        $owner = User::factory()->owner()->create();

        $this->post("/{$owner->restaurant->slug}/login", ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect("/{$owner->restaurant->slug}/admin");

        $this->assertAuthenticatedAs($owner);
    }

    public function test_super_admin_lands_on_restaurant_list(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/super/restaurants');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $this->post("/{$owner->restaurant->slug}/login", ['email' => $owner->email, 'password' => 'sai'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->post('/login', ['email' => 'a@b.vn', 'password' => 'sai']);
        }

        $this->post('/login', ['email' => 'a@b.vn', 'password' => 'sai'])->assertTooManyRequests();
    }

    public function test_user_of_a_locked_restaurant_cannot_log_in(): void
    {
        $owner = User::factory()->owner(Restaurant::factory()->locked()->create())->create();

        $this->post("/{$owner->restaurant->slug}/login", ['email' => $owner->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('ui.login.locked')]);

        $this->assertGuest();
    }

    public function test_logged_in_user_of_a_restaurant_locked_later_is_stopped(): void
    {
        $restaurant = Restaurant::factory()->locked()->create();
        $owner = User::factory()->owner($restaurant)->create();

        $this->actingAs($owner)->get("/{$restaurant->slug}/admin")->assertForbidden();
    }

    public function test_logout(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/logout')->assertRedirect("/{$owner->restaurant->slug}/login");

        $this->assertGuest();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();

        $base = "/{$owner->restaurant->slug}";

        $this->post("$base/forgot-password", ['email' => $owner->email])->assertSessionHas('status');

        Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $notification) use ($owner, $base) {
            $mail = $notification->toMail($owner);
            $this->assertSame(__('ui.password.mail_subject'), $mail->subject);
            $this->assertStringContainsString("$base/reset-password/", $mail->actionUrl);
            $this->get($mail->actionUrl)->assertOk()->assertSee($owner->restaurant->name);

            $this->post("$base/reset-password", [
                'token' => $notification->token,
                'email' => $owner->email,
                'password' => 'mat-khau-moi',
                'password_confirmation' => 'mat-khau-moi',
            ])->assertRedirect("$base/login");

            return true;
        });

        $this->assertTrue(Hash::check('mat-khau-moi', $owner->fresh()->password));
    }

    public function test_forgot_password_does_not_reveal_whether_an_email_exists(): void
    {
        $this->post('/forgot-password', ['email' => 'khong-co@ai.vn'])
            ->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_main_login_is_only_for_super_admins(): void
    {
        $owner = User::factory()->owner()->create();

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
        $this->get('/login')->assertSee(__('ui.login.super_lead'));
    }

    public function test_restaurant_login_only_accepts_its_own_accounts(): void
    {
        $owner = User::factory()->owner()->create();
        $other = Restaurant::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->post("/{$other->slug}/login", ['email' => $owner->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->post("/{$other->slug}/login", ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_restaurant_login_page_shows_its_name_and_colors(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Quán Gió', 'theme' => ['primary' => '#123456', 'secondary' => '#abcdef']]);

        $this->get("/{$restaurant->slug}/login")
            ->assertOk()
            ->assertSee('Quán Gió')
            ->assertSee('#123456', false)
            ->assertDontSee(__('ui.login.super_lead'))
            ->assertSee('action="'.url("/{$restaurant->slug}/login").'"', false);
    }

    public function test_guest_in_a_restaurant_is_sent_to_that_restaurant_login(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->get("/{$restaurant->slug}/admin/profile")->assertRedirect("/{$restaurant->slug}/login");
        $this->get('/super/restaurants')->assertRedirect('/login');
    }

    public function test_forgot_password_of_another_restaurant_sends_nothing(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $other = Restaurant::factory()->create();

        $this->post("/{$other->slug}/forgot-password", ['email' => $owner->email])->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_profile_can_be_updated(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->put("/{$owner->restaurant->slug}/admin/profile", ['name' => 'Tên mới', 'email' => 'moi@quan.vn'])
            ->assertSessionHas('status');

        $this->assertSame('Tên mới', $owner->fresh()->name);
    }

    public function test_profile_update_cannot_change_role_or_tenant(): void
    {
        $owner = User::factory()->owner()->create();
        $other = Restaurant::factory()->create();

        $this->actingAs($owner)->put("/{$owner->restaurant->slug}/admin/profile", [
            'name' => 'A', 'email' => $owner->email, 'restaurant_id' => $other->id, 'is_super_admin' => 1,
        ]);

        $fresh = $owner->fresh();
        $this->assertSame($owner->restaurant_id, $fresh->restaurant_id);
        $this->assertFalse($fresh->is_super_admin);
    }

    public function test_password_change_needs_the_current_password(): void
    {
        $owner = User::factory()->owner()->create();
        $url = "/{$owner->restaurant->slug}/admin/profile/password";

        $this->actingAs($owner)->put($url, [
            'current_password' => 'sai', 'password' => 'mat-khau-moi', 'password_confirmation' => 'mat-khau-moi',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($owner)->put($url, [
            'current_password' => 'password', 'password' => 'mat-khau-moi', 'password_confirmation' => 'mat-khau-moi',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('mat-khau-moi', $owner->fresh()->password));
    }

    public function test_password_fields_have_a_show_hide_button(): void
    {
        $restaurant = Restaurant::factory()->create();

        foreach (['/login', "/{$restaurant->slug}/login"] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('data-password-toggle', false)
                ->assertSee('aria-controls="f-password"', false)
                ->assertSee('aria-label="'.__('ui.password.show').'"', false);
        }
    }
}
