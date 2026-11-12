<?php

namespace App\Modules\DineIn\Models;

use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Models\User;
use App\Modules\DineIn\LineStatus;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Takeaway\DiscountType;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Một lượt khách ở một bàn: mở khi gọi món đầu tiên, đóng khi khách về. */
class DiningTable extends Model
{
    use BelongsToRestaurant;

    protected $table = 'dinein_tables';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'table_number' => 'integer',
            'closed_at' => 'immutable_datetime',
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'dinein_table_id')->orderBy('round')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Đặt bàn trước của khách đang ngồi bàn này (xếp bàn lúc khách đến). */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(CustomerOffer::class, 'customer_offer_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'dinein_table_id')->latest('id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** Tạm tính: các món chưa huỷ. */
    public function subtotal(): int
    {
        return $this->lines->filter(fn (OrderLine $line) => $line->status->billable())->sum(fn (OrderLine $line) => $line->total());
    }

    /**
     * Phiếu tạm tính: các món chưa huỷ, gộp mọi lượt; cùng món, cùng tuỳ chọn, cùng giá thì cộng số lượng.
     *
     * @return Collection<int, array{name: string, options: list<string>, quantity: int, unit: int, total: int}>
     */
    public function billLines(): Collection
    {
        return $this->lines->filter(fn (OrderLine $line) => $line->status->billable())
            ->groupBy(fn (OrderLine $line) => $line->name.'|'.implode(';', $line->optionLines()).'|'.$line->unit_price)
            ->map(fn ($lines) => [
                'name' => $lines->first()->name,
                'options' => $lines->first()->optionLines(),
                'quantity' => $lines->sum('quantity'),
                'unit' => $lines->first()->unit_price,
                'total' => $lines->sum(fn (OrderLine $line) => $line->total()),
            ])
            ->values();
    }

    /** Số tiền giảm trên phiếu (theo tạm tính hiện tại; đóng bàn thì lưu lại). */
    public function discount(): int
    {
        if ($this->closed_at !== null) {
            return $this->discount_amount;
        }

        return $this->discount_type?->amountOff((int) $this->discount_value, $this->subtotal()) ?? 0;
    }

    /** Còn phải trả: tạm tính trừ giảm giá. */
    public function total(): int
    {
        return $this->subtotal() - $this->discount();
    }

    public function nextRound(): int
    {
        return (int) $this->lines()->max('round') + 1;
    }

    /** Còn món chưa mang ra thì chưa đóng bàn được. */
    public function hasPendingLines(): bool
    {
        return $this->lines()->whereIn('status', [LineStatus::New, LineStatus::Done])->exists();
    }
}
