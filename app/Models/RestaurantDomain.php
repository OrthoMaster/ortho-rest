<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Không dùng BelongsToRestaurant: bảng này được đọc để tìm ra nhà hàng hiện
 * tại, nên chạy trước khi có tenant.
 */
#[Fillable(['domain', 'verified_at'])]
class RestaurantDomain extends Model
{
    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    /** Đưa giá trị người dùng nhập (có thể kèm https://, đường dẫn) về dạng host. */
    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $value);

        return rtrim((string) preg_replace('#[/:?\#].*$#', '', $value), '.');
    }
}
