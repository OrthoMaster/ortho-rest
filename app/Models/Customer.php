<?php

namespace App\Models;

use App\Modules\Reservation\Models\Reservation;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function takeawayOrders(): HasMany
    {
        return $this->hasMany(TakeawayOrder::class);
    }

    /** Tìm theo số điện thoại trong quán hiện tại, cập nhật tên và email mới nhất. */
    public static function remember(string $name, string $phone, ?string $email = null): self
    {
        return static::updateOrCreate(['phone' => $phone], array_filter([
            'name' => $name,
            'email' => $email,
        ]));
    }
}
