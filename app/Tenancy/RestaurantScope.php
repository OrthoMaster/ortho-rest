<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Lọc theo nhà hàng hiện tại. Khi chưa có nhà hàng hiện tại, truy vấn trả về
 * rỗng thay vì trả về dữ liệu của mọi quán: quên đặt tenant thì không lộ dữ liệu.
 * Chỗ cần đọc xuyên quán (super admin) phải gọi rõ forRestaurant().
 */
class RestaurantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('restaurant_id'), $tenantId);
    }
}
