<?php

namespace App\Http\Controllers\Auth;

use App\Auth\DeviceGuard;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, self::scope()], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (tenant()?->isLocked()) {
            $this->reject($request, __('ui.login.locked'));
        }

        $request->session()->regenerate();

        if ($this->devices->admit($request->user(), $request) === null) {
            $this->reject($request, __('ui.devices.blocked'));
        }

        return redirect()->intended($request->user()->homeUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $loginUrl = $request->user()->loginUrl();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($loginUrl);
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

        throw ValidationException::withMessages(['email' => $message]);
    }
}
