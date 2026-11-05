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

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
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

        $this->post('/login', ['email' => $owner->email, 'password' => 'sai'])
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

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
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
        $this->actingAs(User::factory()->owner()->create())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();

        $this->post('/forgot-password', ['email' => $owner->email])->assertSessionHas('status');

        Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $notification) use ($owner) {
            $mail = $notification->toMail($owner);
            $this->assertSame(__('ui.password.mail_subject'), $mail->subject);
            $this->get($mail->actionUrl)->assertOk();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $owner->email,
                'password' => 'mat-khau-moi',
                'password_confirmation' => 'mat-khau-moi',
            ])->assertRedirect('/login');

            return true;
        });

        $this->assertTrue(Hash::check('mat-khau-moi', $owner->fresh()->password));
    }

    public function test_forgot_password_does_not_reveal_whether_an_email_exists(): void
    {
        $this->post('/forgot-password', ['email' => 'khong-co@ai.vn'])
            ->assertSessionHas('status', __('passwords.sent'));
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
}
