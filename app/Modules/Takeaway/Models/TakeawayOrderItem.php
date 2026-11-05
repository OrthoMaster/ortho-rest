<?php

namespace App\Modules\Takeaway\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class TakeawayOrderItem extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer'];
    }

    public function lineTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
