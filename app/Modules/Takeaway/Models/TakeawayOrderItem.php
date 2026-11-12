<?php

namespace App\Modules\Takeaway\Models;

use App\Modules\Menu\HasChosenOptions;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** Một món của đơn mang đi. Giá đã gồm tiền cộng thêm của tuỳ chọn. */
class TakeawayOrderItem extends Model
{
    use BelongsToRestaurant, HasChosenOptions;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer', 'options' => 'array'];
    }

    /** Tên kèm tuỳ chọn, dùng trong lịch sử sửa đơn: "Cơm tấm (Thịt: Sườn cọng)". */
    public function label(): string
    {
        return $this->name.($this->optionLines() !== [] ? ' ('.implode('; ', $this->optionLines()).')' : '');
    }

    public function lineTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
