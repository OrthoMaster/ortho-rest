<ul class="booking-list">
    @foreach ($rows as $reservation)
        <li @class(['row-link', 'is-pending' => $reservation->status === \App\Modules\Reservation\ReservationStatus::Pending, 'is-done' => in_array($reservation->status, [\App\Modules\Reservation\ReservationStatus::Completed, \App\Modules\Reservation\ReservationStatus::NoShow], true)])>
            <span class="booking-time">{{ $reservation->reserved_at->format('H:i') }}</span>
            <div class="booking-main">
                <a href="{{ route('tenant.admin.reservation.show', $reservation) }}">{{ $reservation->customer->name }}</a>
                <small>{{ __('reservation.party', ['count' => $reservation->party_size]) }}, {{ $reservation->area->name }}@if ($reservation->source === \App\Modules\Reservation\ReservationSource::Online), {{ $reservation->source->label() }}@endif</small>
                <small><a class="booking-phone" href="tel:{{ $reservation->customer->phone }}">{{ $reservation->customer->phone }}</a></small>
            </div>
            <span class="badge {{ $reservation->status->badge() }}">{{ $reservation->status->label() }}</span>
            @php($nextSteps = $reservation->status->nextFor(auth()->user()))
            @if ($nextSteps !== [])
                <div class="row-tools">
                    @component('partials.kebab', ['label' => __('reservation.actions_for', ['name' => $reservation->customer->name])])
                        @foreach ($nextSteps as $next)
                            <form method="post" action="{{ route('tenant.admin.reservation.status', $reservation) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $next->value }}">
                                <button type="submit">{{ $next->actionLabel() }}</button>
                            </form>
                        @endforeach
                    @endcomponent
                </div>
            @endif
        </li>
    @endforeach
</ul>
