<?php

namespace App\Tenancy;

use App\Models\Restaurant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Logo lưu ở theme.logo. Không nhận SVG vì file SVG có thể chứa script. */
final class RestaurantLogo
{
    public const RULES = ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

    public static function store(Restaurant $restaurant, UploadedFile $file): void
    {
        $old = $restaurant->theme['logo'] ?? null;
        $path = $file->store("restaurants/{$restaurant->getKey()}", self::disk());

        $restaurant->update(['theme' => [...($restaurant->theme ?? []), 'logo' => $path]]);

        self::deleteFile($old);
    }

    public static function remove(Restaurant $restaurant): void
    {
        $theme = $restaurant->theme ?? [];
        $old = $theme['logo'] ?? null;
        unset($theme['logo']);

        $restaurant->update(['theme' => $theme ?: null]);

        self::deleteFile($old);
    }

    public static function url(Restaurant $restaurant): ?string
    {
        $path = $restaurant->theme['logo'] ?? null;

        return $path ? Storage::disk(self::disk())->url($path) : null;
    }

    private static function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk(self::disk())->delete($path);
        }
    }

    private static function disk(): string
    {
        return config('liresto.uploads_disk');
    }
}
