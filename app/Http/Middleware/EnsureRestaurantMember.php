<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\LoginController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()->restaurant_id === tenant()->getKey(), 403);

        // Tài khoản vừa bị ngừng, hoặc quán tắt Nội bộ: đăng xuất ngay, quay về trang đăng nhập.
        if ($message = LoginController::blockedReason($request->user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect(tenant_route('login'))->withErrors(['login' => $message]);
        }

        return $next($request);
    }
}
