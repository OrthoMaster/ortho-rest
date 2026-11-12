<?php

namespace App\Models;

use App\Modules\DineIn\Models\DiningTable;
use App\Modules\Takeaway\DiscountType;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ưu đãi lần sau của một khách (ví dụ bù cho sự cố): giảm % hoặc số tiền, dùng một lần.
 * Khách không phải giữ gì: lần sau gắn khách theo số điện thoại là thấy ưu đãi.
 */
class CustomerOffer extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'expires_on' => 'immutable_date',
            'used_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dinein_table_id');
    }

    /** Chưa dùng và còn hạn. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()));
    }

    /** "Giảm 10% · Món ra chậm · hạn 12/02/2027" */
    public function summary(): string
    {
        return collect([
            $this->type->label($this->value),
            $this->reason,
            $this->expires_on ? __('customers.offer_until', ['date' => $this->expires_on->format('d/m/Y')]) : null,
        ])->filter()->implode(' · ');
    }
}
