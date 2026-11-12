<?php

namespace App\Modules\Takeaway\Models;

use App\Modules\Menu\HasChosenOptions;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một món của đơn mang đi. Giá đã gồm tiền cộng thêm của tuỳ chọn. */
class TakeawayOrderItem extends Model
{
    use BelongsToRestaurant, HasChosenOptions;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer', 'options' => 'array', 'done_at' => 'immutable_datetime'];
    }

    /** Tên kèm tuỳ chọn, dùng trong lịch sử sửa đơn: "Cơm tấm (Thịt: Sườn cọng)". */
    public function label(): string
    {
        return $this->name.($this->optionLines() !== [] ? ' ('.implode('; ', $this->optionLines()).')' : '');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TakeawayOrder::class, 'takeaway_order_id');
    }

    public function lineTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
