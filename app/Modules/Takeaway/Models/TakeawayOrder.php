<?php

namespace App\Modules\Takeaway\Models;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Takeaway\DiscountType;
use App\Modules\Takeaway\OrderStatus;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TakeawayOrder extends Model
{
    use BelongsToRestaurant;

    // Chữ đầu mã đơn cho biết loại đơn. Đơn tại chỗ sau này dùng chữ khác.
    public const CODE_PREFIX = 'M';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'pickup_at' => 'datetime',
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
        ];
    }

    /** Mã hiển thị: M001, đủ 3 số, từ 1000 đơn một ngày thì thành 4 số. */
    public function code(): string
    {
        return self::CODE_PREFIX.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    /** Chưa giao và chưa huỷ thì khách còn đổi được. */
    public function isEditable(): bool
    {
        return $this->status->next() !== [];
    }

    public function edits(): HasMany
    {
        return $this->hasMany(TakeawayOrderEdit::class)->latest('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TakeawayOrderItem::class);
    }
}
