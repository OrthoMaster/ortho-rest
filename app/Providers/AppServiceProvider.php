<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(ModuleManager::class);
    }

    public function boot(): void
    {
        // Giảm theo % hoặc số tiền trên đơn. Nhập mã không cần quyền này.
        Gate::define('takeaway.direct-discount', fn (User $user): bool => $user->role === UserRole::Owner
            || in_array(
                $user->role?->value,
                app(ModuleManager::class)->settings($user->restaurant, 'takeaway')['direct_discount_roles'],
                true,
            ));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject(__('ui.password.mail_subject'))
            ->line(__('ui.password.mail_intro'))
            ->action(__('ui.password.mail_action'), route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line(__('ui.password.mail_expire', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('ui.password.mail_ignore')));
    }
}
