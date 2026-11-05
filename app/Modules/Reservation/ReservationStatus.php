<?php

namespace App\Modules\Reservation;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Seated = 'seated';
    case Completed = 'completed';

    public function label(): string
    {
        return __('reservation.status.'.$this->value);
    }

    public function actionLabel(): string
    {
        return __('reservation.action.'.$this->value);
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Rejected, self::Cancelled],
            self::Confirmed => [self::Seated, self::NoShow, self::Cancelled],
            self::Seated => [self::Completed],
            default => [],
        };
    }

    public function canBecome(self $status): bool
    {
        return in_array($status, $this->next(), true);
    }

    /** Còn giữ chỗ trong khu vực. */
    public function holdsSeats(): bool
    {
        return in_array($this, self::active(), true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed, self::Seated];
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-warn',
            self::Confirmed, self::Seated => 'badge-ok',
            self::Rejected, self::NoShow => 'badge-bad',
            default => '',
        };
    }
}
