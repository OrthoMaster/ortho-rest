<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\ModuleManager;
use App\Support\Permissions;
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
        // Quyền trong config/permissions.php: chủ quán luôn có, người khác theo chức vụ.
        Gate::before(fn (User $user, string $ability) => Permissions::exists($ability) ? Permissions::allows($user, $ability) : null);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('login', $request->input('email'))).'|'.$request->ip(),
        ));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject(__('ui.password.mail_subject'))
            ->line(__('ui.password.mail_intro'))
            ->action(__('ui.password.mail_action'), $notifiable->is_super_admin || $notifiable->restaurant === null
                ? route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()])
                : route('tenant.password.reset', [
                    'resto' => $notifiable->restaurant->slug,
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]))
            ->line(__('ui.password.mail_expire', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('ui.password.mail_ignore')));
    }
}
