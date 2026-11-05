<?php

namespace App\Modules\Reservation;

enum ReservationSource: string
{
    case Online = 'online';
    case Phone = 'phone';
    case WalkIn = 'walk_in';

    public function label(): string
    {
        return __('reservation.source.'.$this->value);
    }

    public function initialStatus(): ReservationStatus
    {
        return match ($this) {
            self::Online => ReservationStatus::Pending,
            self::Phone => ReservationStatus::Confirmed,
            self::WalkIn => ReservationStatus::Seated,
        };
    }
}
