<div class="status-actions">
    @foreach ($reservation->status->nextFor(auth()->user()) as $next)
        @include('modules.reservation.status-form', ['reservation' => $reservation, 'next' => $next,
            'class' => in_array($next, [\App\Modules\Reservation\ReservationStatus::Rejected, \App\Modules\Reservation\ReservationStatus::Cancelled, \App\Modules\Reservation\ReservationStatus::NoShow], true) ? 'btn btn-sm btn-danger' : ($loop->first ? 'btn btn-sm' : 'btn btn-sm btn-quiet')])
    @endforeach
</div>
