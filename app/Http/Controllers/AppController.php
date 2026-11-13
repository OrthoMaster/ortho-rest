<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Tenancy\RestaurantTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Ứng dụng cài được của từng quán (PWA): thông tin cài đặt, icon theo logo quán,
 * và lưu máy đã bật thông báo đẩy.
 */
class AppController extends Controller
{
    public const ICON_SIZES = [180, 192, 512];

    public function manifest(): JsonResponse
    {
        $restaurant = tenant();
        $scope = '/'.$restaurant->slug.'/';

        return response()->json([
            'id' => $scope.'admin',
            'name' => $restaurant->name,
            'short_name' => $restaurant->name,
            'lang' => app()->getLocale(),
            'start_url' => $scope.'admin',
            'scope' => $scope,
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $restaurant->theme['primary'] ?? RestaurantTheme::PRIMARY,
            'icons' => collect([192, 512])->map(fn (int $size) => [
                'src' => route('tenant.app-icon', ['size' => $size, 'v' => self::iconVersion()]),
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any',
            ])->all(),
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)->header('Content-Type', 'application/manifest+json');
    }

    /** Icon vuông: logo quán đặt giữa nền trắng; chưa có logo thì dùng icon mặc định. */
    public function icon(int $size): Response
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);

        $path = tenant()->theme['logo'] ?? null;
        $disk = Storage::disk(config('liresto.uploads_disk'));
        $source = $path && $disk->exists($path) ? @imagecreatefromstring($disk->get($path)) : false;

        $canvas = imagecreatetruecolor($size, $size);

        if ($source) {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            // Chừa lề 12% để Android cắt bo tròn không mất logo.
            $box = (int) round($size * 0.76);
            $ratio = min($box / imagesx($source), $box / imagesy($source));
            [$width, $height] = [(int) round(imagesx($source) * $ratio), (int) round(imagesy($source) * $ratio)];
            imagecopyresampled($canvas, $source, intdiv($size - $width, 2), intdiv($size - $height, 2), 0, 0, $width, $height, imagesx($source), imagesy($source));
        } else {
            $default = imagecreatefrompng(public_path('images/app-icon.png'));
            imagecopyresampled($canvas, $default, 0, 0, 0, 0, $size, $size, imagesx($default), imagesy($default));
        }

        ob_start();
        imagepng($canvas);

        return response(ob_get_clean(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Máy này bật thông báo: lưu (hoặc chuyển sang user đang đăng nhập nếu máy đã có). */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth' => ['required', 'string', 'max:60'],
        ]);

        $subscription = PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        // Đăng xuất thì máy này thôi nhận thông báo của người đó.
        $request->session()->put('push_subscription', $subscription->id);

        return response()->json(['ok' => true]);
    }

    /** Đổi logo thì đổi đường dẫn icon để máy tải icon mới. */
    public static function iconVersion(): string
    {
        return substr(md5((string) (tenant()->theme['logo'] ?? 'default')), 0, 8);
    }
}
