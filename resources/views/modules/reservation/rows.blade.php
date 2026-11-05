<ul class="booking-list">
    @foreach ($rows as $reservation)
        <li class="row-link">
            <span class="booking-time">{{ $reservation->reserved_at->format('H:i') }}</span>
            <div class="booking-main">
                <a href="{{ route('tenant.admin.reservation.show', $reservation) }}">{{ $reservation->customer->name }}</a>
                <small>{{ __('reservation.party', ['count' => $reservation->party_size]) }}, {{ $reservation->area->name }}</small>
                <small><a class="booking-phone" href="tel:{{ $reservation->customer->phone }}">{{ $reservation->customer->phone }}</a></small>
            </div>
            <span class="badge {{ $reservation->status->badge() }}">{{ $reservation->status->label() }}</span>
            @if ($reservation->status->next() !== [])
                <div class="row-tools">
                    @component('partials.kebab', ['label' => __('reservation.actions_for', ['name' => $reservation->customer->name])])
                        @foreach ($reservation->status->next() as $next)
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
