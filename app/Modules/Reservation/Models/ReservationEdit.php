<?php

namespace App\Modules\Reservation\Models;

use App\Models\User;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần sửa đặt bàn: ai sửa, lúc nào, đổi gì. Chỉ thêm, không sửa. */
class ReservationEdit extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
