<?php

namespace App\Http\Middleware;

use App\Auth\DeviceGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Thiết bị bị xoá thì đăng xuất ngay; thiết bị lạ thì chỉ vào được khi user chưa khoá. */
class TrackDevice
{
    public function __construct(private DeviceGuard $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admin đăng nhập thay chủ quán thì dùng thiết bị của super admin.
        if ($user === null || $request->session()->has('impersonator_id')) {
            return $next($request);
        }

        $id = $request->session()->get(DeviceGuard::SESSION_USER_KEY) === $user->getKey()
            ? $request->session()->get(DeviceGuard::SESSION_KEY)
            : null;
        $device = $id ? $user->devices()->find($id) : null;

        if ($device !== null) {
            $this->devices->touch($device, $request);

            return $next($request);
        }

        if ($id === null && $this->devices->admit($user, $request) !== null) {
            return $next($request);
        }

        $loginUrl = $user->loginUrl();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = __($id === null ? 'ui.devices.blocked' : 'ui.devices.revoked');

        return $request->expectsJson()
            ? response()->json(['message' => $message], 401)
            : redirect($loginUrl)->with('error', $message);
    }
}
