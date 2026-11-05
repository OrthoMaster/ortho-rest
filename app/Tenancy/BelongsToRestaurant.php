<?php

namespace App\Tenancy;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Gắn cho mọi model có cột restaurant_id. */
trait BelongsToRestaurant
{
    public static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope(new RestaurantScope);

        static::creating(function ($model): void {
            $tenantId = app(TenantContext::class)->id();

            $model->restaurant_id ??= $tenantId;

            if ($model->restaurant_id === null) {
                throw new LogicException('Không tạo được '.static::class.' khi chưa có nhà hàng.');
            }

            if ($tenantId !== null && (int) $model->restaurant_id !== $tenantId) {
                throw new LogicException('Không được ghi dữ liệu sang nhà hàng khác.');
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /** Bỏ qua nhà hàng hiện tại, chỉ dùng cho super admin và mã hệ thống. */
    public function scopeForRestaurant(Builder $query, Restaurant|int $restaurant): Builder
    {
        return $query->withoutGlobalScope(RestaurantScope::class)
            ->where($this->qualifyColumn('restaurant_id'), $restaurant instanceof Restaurant ? $restaurant->getKey() : $restaurant);
    }
}
