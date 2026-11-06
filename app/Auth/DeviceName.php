<?php

namespace App\Auth;

/** Tên dễ đọc từ User-Agent, ví dụ "Safari trên iPhone". Chỉ cần đủ để người dùng nhận ra máy. */
class DeviceName
{
    private const SYSTEMS = [
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'Macintosh' => 'Mac',
        'CrOS' => 'Chromebook',
        'Linux' => 'Linux',
    ];

    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser' => 'Samsung Internet',
        'Zalo' => 'Zalo',
        'FBAN' => 'Facebook',
        'Firefox/' => 'Firefox',
        'FxiOS' => 'Firefox',
        'CriOS' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    public static function from(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;
        $find = function (array $map) use ($userAgent): ?string {
            foreach ($map as $needle => $name) {
                if (str_contains($userAgent, $needle)) {
                    return $name;
                }
            }

            return null;
        };

        $system = $find(self::SYSTEMS);
        $browser = $find(self::BROWSERS);

        return match (true) {
            $system && $browser => __('ui.devices.name', ['browser' => $browser, 'system' => $system]),
            $system !== null => $system,
            $browser !== null => $browser,
            default => __('ui.devices.unknown'),
        };
    }
}
