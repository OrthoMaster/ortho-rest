<?php

namespace App\Auth;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Mỗi trình duyệt giữ một cookie lâu dài làm dấu của thiết bị. Đăng nhập trên
 * thiết bị lạ thì tạo thiết bị mới, trừ khi user đã khoá danh sách thiết bị.
 */
class DeviceGuard
{
    public const COOKIE = 'liresto_device';

    public const SESSION_KEY = 'device_id';

    /** Thiết bị trong session là của user nào, để đổi người đăng nhập thì cấp lại. */
    public const SESSION_USER_KEY = 'device_user_id';

    private const COOKIE_MINUTES = 60 * 24 * 365 * 5;

    private const TOUCH_MINUTES = 5;

    /** Trả về null khi thiết bị lạ mà user chỉ cho các thiết bị đã có. */
    public function admit(User $user, Request $request): ?UserDevice
    {
        $token = (string) $request->cookie(self::COOKIE);
        $device = $token === '' ? null : $user->devices()->where('token_hash', hash('sha256', $token))->first();

        if ($device === null) {
            if ($user->devices_locked) {
                return null;
            }

            if ($token === '') {
                $token = Str::random(48);
                Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES);
            }

            $device = $user->devices()->create([
                'token_hash' => hash('sha256', $token),
                'name' => DeviceName::from($request->userAgent()),
            ]);
        }

        $request->session()->put([self::SESSION_KEY => $device->getKey(), self::SESSION_USER_KEY => $user->getKey()]);
        $this->touch($device, $request, force: true);

        return $device;
    }

    public function touch(UserDevice $device, Request $request, bool $force = false): void
    {
        $sessionId = $request->session()->getId();

        if ($force || $device->session_id !== $sessionId || ! $device->last_seen_at?->gt(now()->subMinutes(self::TOUCH_MINUTES))) {
            $device->forceFill([
                'name' => DeviceName::from($request->userAgent()),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
                'session_id' => $sessionId,
                'last_seen_at' => now(),
            ])->save();
        }
    }

    /**
     * Đăng xuất ngay thiết bị đó: lần bấm kế tiếp trên máy đó, TrackDevice thấy
     * thiết bị không còn và đăng xuất kèm thông báo. Bỏ luôn "ghi nhớ đăng nhập".
     */
    public function revoke(UserDevice $device): void
    {
        $device->user->forceFill(['remember_token' => Str::random(60)])->save();
        $device->delete();
    }
}
