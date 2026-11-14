<?php

namespace App\Http\Controllers\Auth;

use App\Auth\DeviceGuard;
use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Models\User;
use App\Push\Notify;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Hai trang đăng nhập dùng chung controller: /login chỉ cho super admin,
 * /{quán}/login chỉ cho tài khoản của quán đó.
 */
class LoginController extends Controller
{
    public function __construct(private DeviceGuard $devices) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...self::credentials($data['login']), 'password' => $data['password'], self::scope()], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        if (tenant()?->isLocked()) {
            $this->reject($request, __('ui.login.locked'));
        }

        if ($message = self::blockedReason($request->user())) {
            $this->reject($request, $message);
        }

        $request->session()->regenerate();

        $device = $this->devices->admit($request->user(), $request);

        if ($device === null) {
            $this->reject($request, __('ui.devices.blocked'));
        }

        // Đăng nhập từ máy mới (đã có máy khác): báo các máy đang bật thông báo của chính người này.
        if ($device->wasRecentlyCreated && $request->user()->devices()->count() > 1) {
            Notify::users([$request->user()], [
                'title' => __('ui.devices.push_new', ['name' => $device->name]),
                'body' => __('ui.devices.push_new_body', ['time' => now()->format('H:i d/m'), 'ip' => $request->ip()]),
                'url' => route('tenant.admin.profile.edit').'#devices',
                'tag' => 'device-'.$device->getKey(),
            ], skipActor: false);
        }

        return redirect()->intended($request->user()->homeUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $loginUrl = $request->user()->loginUrl();

        // Máy này thôi nhận thông báo của người vừa đăng xuất.
        if ($subscription = $request->session()->get('push_subscription')) {
            PushSubscription::whereKey($subscription)->where('user_id', $request->user()->id)->delete();
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($loginUrl);
    }

    /**
     * Trang của quán: email hoặc số điện thoại. Trang super admin: chỉ email.
     *
     * @return array<string, string>
     */
    public static function credentials(string $login): array
    {
        $login = trim($login);

        return tenant() === null || str_contains($login, '@')
            ? ['email' => Str::lower($login)]
            : ['phone' => Phone::normalize($login)];
    }

    /** Vì sao một người của quán không được vào: tài khoản đã ngừng, hoặc quán chưa bật Nội bộ (chỉ chủ quán làm). */
    public static function blockedReason(User $user): ?string
    {
        if ($user->is_super_admin || $user->isOwner()) {
            return null;
        }

        return match (true) {
            ! $user->is_active => __('staff.inactive_login'),
            ! $user->restaurant?->hasModule('staff') => __('staff.module_off_login'),
            default => null,
        };
    }

    /** Điều kiện tài khoản theo trang đăng nhập đang dùng. Dùng cả cho quên mật khẩu. */
    public static function scope(): \Closure
    {
        $restaurant = tenant();

        return $restaurant
            ? fn (Builder $query) => $query->where('restaurant_id', $restaurant->getKey())->where('is_super_admin', false)
            : fn (Builder $query) => $query->where('is_super_admin', true);
    }

    private function reject(Request $request, string $message): never
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        throw ValidationException::withMessages(['login' => $message]);
    }
}
