<?php

namespace App\Modules\Reservation\Models;

use App\Models\Customer;
use App\Models\User;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    /** Mã riêng trong link khách xem, tự huỷ đặt bàn (không đoán được, không lộ số thứ tự). */
    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            $reservation->public_token ??= Str::random(24);
        });
        static::replicating(function (Reservation $reservation): void {
            $reservation->public_token = null;
        });
    }

    /** Link khách xem lại đặt bàn của mình. */
    public function publicUrl(): string
    {
        if ($this->public_token === null) {
            $this->forceFill(['public_token' => Str::random(24)])->saveQuietly();
        }

        return tenant_route('public.booking.status', ['token' => $this->public_token]);
    }

    /** Mã ngắn đọc cho quán qua điện thoại. */
    public function code(): string
    {
        return strtoupper(substr((string) $this->public_token, 0, 6));
    }

    /** Khách tự huỷ được khi đang chờ hoặc đã nhận và chưa tới giờ. */
    public function guestCanCancel(): bool
    {
        return in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true) && $this->reserved_at->isFuture();
    }

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Bàn đã xếp lúc khách đến (Lấy đơn tại bàn). */
    public function diningTable(): HasOne
    {
        return $this->hasOne(DiningTable::class)->latestOfMany();
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReservationStatusLog::class)->orderBy('id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(ReservationEdit::class)->orderBy('id');
    }

    public function activityArea(): string
    {
        return 'reservation';
    }

    public function activityName(): string
    {
        return __('activity.names.reservation', [
            'customer' => $this->customer?->name,
            'time' => $this->reserved_at->format('H:i d/m'),
        ]);
    }

    public function activityFields(): array
    {
        // Sửa giờ, số khách... được ghi trong ReservationBooking::update cùng lịch sử sửa.
        return ['status' => 'plain'];
    }
}
