<?php

namespace App\Models;

use App\Modules\DineIn\Models\DiningTable;
use App\Modules\Takeaway\DiscountType;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ưu đãi lần sau (ví dụ bù cho sự cố): giảm % hoặc số tiền, dùng một lần, tại bàn hoặc cho đơn mang đi.
 * Gắn với khách thì lần sau gắn khách theo số điện thoại là thấy. Khách không để lại thông tin
 * thì là voucher không tên: khách quét mã QR để lưu trang ưu đãi trên điện thoại, lần sau đưa mã.
 */
class CustomerOffer extends Model
{
    use BelongsToRestaurant;

    /** Chữ dùng cho mã: bỏ 0, O, 1, I, L để đọc không nhầm. */
    private const CODE_CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (CustomerOffer $offer): void {
            $offer->code ??= self::newCode($offer->restaurant_id);
            $offer->token ??= self::newToken();
        });
    }

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'expires_on' => 'immutable_date',
            'used_at' => 'immutable_datetime',
        ];
    }

    /** Mã 6 chữ, không trùng mã ưu đãi hay mã giảm giá khác của quán. */
    public static function newCode(int $restaurantId): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_CHARS[random_int(0, strlen(self::CODE_CHARS) - 1)];
            }
        } while (DB::table('customer_offers')->where('restaurant_id', $restaurantId)->where('code', $code)->exists()
            || DB::table('discount_codes')->where('restaurant_id', $restaurantId)->where('code', $code)->exists());

        return $code;
    }

    public static function newToken(): string
    {
        return Str::random(32);
    }

    /** Ưu đãi còn dùng được theo mã khách đưa (không phân biệt hoa thường, bỏ khoảng trắng). */
    public static function usableCode(?string $code): ?self
    {
        $code = strtoupper(preg_replace('/\s+/', '', (string) $code));

        return $code === '' ? null : static::where('code', $code)->usable()->first();
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

    public function usedTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'used_table_id');
    }

    public function usedOrder(): BelongsTo
    {
        return $this->belongsTo(TakeawayOrder::class, 'used_takeaway_order_id');
    }

    /** Chưa dùng và còn hạn. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()));
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isBefore(today());
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && ! $this->isExpired();
    }

    public function markUsed(?DiningTable $table = null, ?TakeawayOrder $order = null): void
    {
        $this->update(['used_at' => now(), 'used_table_id' => $table?->id, 'used_takeaway_order_id' => $order?->id]);
    }

    /** Trả lại ưu đãi (bỏ giảm trên phiếu, đổi mã, huỷ đơn) để dùng lần khác. */
    public function release(): void
    {
        $this->update(['used_at' => null, 'used_table_id' => null, 'used_takeaway_order_id' => null]);
    }

    /** "Chưa dùng", "Đã dùng ở bàn A2 lúc ...", "Hết hạn" */
    public function statusLabel(): string
    {
        return match (true) {
            $this->used_at !== null => __('dinein.voucher_used', ['where' => $this->usedTable?->label
                ? __('dinein.voucher_used_table', ['table' => $this->usedTable->label])
                : __('dinein.voucher_used_order', ['code' => $this->usedOrder?->code() ?? '—']), 'time' => $this->used_at->format('H:i d/m')]),
            $this->isExpired() => __('dinein.voucher_expired'),
            default => __('dinein.voucher_unused'),
        };
    }

    public function statusBadge(): string
    {
        return match (true) {
            $this->used_at !== null => 'badge-muted',
            $this->isExpired() => 'badge-warn',
            default => 'badge-ok',
        };
    }

    /** Trang ưu đãi khách mở bằng mã QR (không cần đăng nhập). */
    public function publicUrl(): string
    {
        return route('tenant.voucher', ['resto' => $this->restaurant->slug, 'token' => $this->token]);
    }

    /** "Giảm 10%" */
    public function valueLabel(): string
    {
        return Str::ucfirst($this->type->label($this->value));
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
