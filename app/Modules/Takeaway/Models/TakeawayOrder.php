<?php

namespace App\Modules\Takeaway\Models;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Takeaway\DiscountType;
use App\Modules\Takeaway\OrderStatus;
use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TakeawayOrder extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'pickup_at' => 'datetime',
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
        ];
    }

    /**
     * Mã hiển thị: chữ đầu của quán, số thứ tự trong ngày nhận, rồi ngày nhận.
     * Ví dụ M001_06. Từ 1000 đơn một ngày thì số thành 4 chữ số.
     */
    public function code(): string
    {
        return $this->code_prefix.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT).'_'.$this->pickup_date->format('d');
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

    public function activityArea(): string
    {
        return 'takeaway';
    }

    public function activityName(): string
    {
        return __('activity.names.order', ['code' => $this->code()]);
    }

    /** Sửa món, giờ nhận... được ghi trong PlaceOrder::update cùng lịch sử sửa đơn. */
    public function activityFields(): array
    {
        return ['status' => 'plain'];
    }
}
