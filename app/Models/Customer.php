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

    protected function casts(): array
    {
        return ['anonymized_at' => 'datetime'];
    }

    /** Mã khách, ít nhất 4 số. Không đổi khi thông tin bị xoá, nên tra lại được từ file Excel đã xuất. */
    public function code(): string
    {
        return str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    /** Đọc mã khách từ chữ người dùng gõ: "0012", "#12"... */
    public static function idFromCode(string $text): ?int
    {
        return preg_match('/^#?0*(\d{1,10})$/', trim($text), $match) && (int) $match[1] > 0 ? (int) $match[1] : null;
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function takeawayOrders(): HasMany
    {
        return $this->hasMany(TakeawayOrder::class);
    }

    /**
     * Tìm theo số điện thoại trong quán hiện tại, cập nhật tên và email mới nhất.
     * Không có số điện thoại thì không nhận ra được khách cũ, nên tạo khách mới.
     */
    public static function remember(string $name, ?string $phone, ?string $email = null): self
    {
        if ($phone === null || $phone === '') {
            return static::create(['name' => $name, 'email' => $email]);
        }

        return static::updateOrCreate(['phone' => $phone], array_filter([
            'name' => $name,
            'email' => $email,
        ]));
    }
}
