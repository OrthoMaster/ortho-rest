<?php

namespace App\Modules\Reservation;

use App\Modules\Reservation\Models\Reservation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationStatusNotification extends Notification
{
    public function __construct(public Reservation $reservation, public string $restaurantName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'reservation.mail.'.$this->reservation->status->value;
        $details = [
            'restaurant' => $this->restaurantName,
            'time' => $this->reservation->reserved_at->format('H:i d/m/Y'),
            'party' => $this->reservation->party_size,
        ];

        return (new MailMessage)
            ->subject(__($key.'_subject', $details))
            ->line(__($key.'_body', $details));
    }
}
