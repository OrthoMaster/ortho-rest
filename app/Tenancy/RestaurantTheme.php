<?php

namespace App\Tenancy;

use App\Models\Restaurant;

/**
 * Đổi màu quán thành biến CSS. Màu quán có thể rất nhạt hoặc rất đậm, nên chữ
 * trên nền màu và màu link được tính ra để luôn đọc được.
 */
final class RestaurantTheme
{
    // Trùng với :root trong public/css/app.css.
    public const PRIMARY = '#a8d0f0';

    public const SECONDARY = '#fbe7a8';

    private const INK = '#1f3a52';

    private const WHITE = '#ffffff';

    private const MIN_TEXT_CONTRAST = 4.5;

    /** Rỗng nếu quán chưa chọn màu riêng: trang dùng màu Liresto. */
    public static function css(Restaurant $restaurant): string
    {
        $primary = $restaurant->theme['primary'] ?? null;
        $secondary = $restaurant->theme['secondary'] ?? null;
        $variables = [];

        if ($primary) {
            $variables['--primary'] = $primary;
            $variables['--on-primary'] = self::textOn($primary);
            $variables['--primary-ink'] = self::readableOnWhite($primary);
        }

        if ($secondary) {
            $variables['--secondary'] = $secondary;
            $variables['--on-secondary'] = self::textOn($secondary);
        }

        return implode(' ', array_map(
            fn (string $name, string $value): string => "$name: $value;",
            array_keys($variables),
            $variables,
        ));
    }

    public static function textOn(string $background): string
    {
        return self::contrast($background, self::INK) >= self::contrast($background, self::WHITE)
            ? self::INK
            : self::WHITE;
    }

    /** Làm đậm dần cho tới khi đủ tương phản trên nền trắng, giữ nguyên sắc màu. */
    public static function readableOnWhite(string $color): string
    {
        $rgb = self::rgb($color);

        while (self::contrast(self::hex($rgb), self::WHITE) < self::MIN_TEXT_CONTRAST) {
            $rgb = array_map(fn (int $channel): int => (int) floor($channel * 0.92), $rgb);
        }

        return self::hex($rgb);
    }

    public static function contrast(string $a, string $b): float
    {
        $lighter = max(self::luminance($a), self::luminance($b));
        $darker = min(self::luminance($a), self::luminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $color): float
    {
        [$red, $green, $blue] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::rgb($color));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /** @return array{int, int, int} */
    private static function rgb(string $color): array
    {
        return array_map('hexdec', str_split(ltrim($color, '#'), 2));
    }

    /** @param array{int, int, int} $rgb */
    private static function hex(array $rgb): string
    {
        return vsprintf('#%02x%02x%02x', $rgb);
    }
}
