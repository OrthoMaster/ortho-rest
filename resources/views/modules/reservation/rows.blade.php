<ul class="booking-list">
    @foreach ($rows as $reservation)
        <li @class(['row-link', 'is-pending' => $reservation->status === \App\Modules\Reservation\ReservationStatus::Pending, 'is-done' => in_array($reservation->status, [\App\Modules\Reservation\ReservationStatus::Completed, \App\Modules\Reservation\ReservationStatus::NoShow], true)])
            data-search-item="{{ $reservation->customer->name }} {{ $reservation->customer->phone }} {{ ltrim((string) $reservation->customer->phone, '0') }}">
            <span class="booking-time">{{ $reservation->reserved_at->format('H:i') }}</span>
            <div class="booking-main">
                <a href="{{ route('tenant.admin.reservation.show', $reservation) }}">{{ $reservation->customer->name }}</a>
                <small>{{ collect([__('reservation.party', ['count' => $reservation->party_size]), $reservation->area?->name, $reservation->source->label()])->filter()->implode(' · ') }}</small>
                {{-- Số đầy đủ chỉ ở trang chi tiết, danh sách chỉ hiện 3 số cuối. --}}
                @if ($reservation->customer->phone)
                    <small class="booking-phone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg><span class="visually-hidden">{{ __('reservation.customer_phone') }}:</span> {{ mask_phone($reservation->customer->phone) }}</small>
                @endif
            </div>
            @php($arrive = ($today ?? false) && $reservation->status === \App\Modules\Reservation\ReservationStatus::Confirmed)
            {{-- Ở tab Hôm nay mọi dòng đều đã xác nhận, chỉ hiện nhãn khi khác. --}}
            @unless ($arrive)
                <span class="badge {{ $reservation->status->badge() }}">{{ $reservation->status->label() }}</span>
            @endunless
            @php($nextSteps = $reservation->status->nextFor(auth()->user()))
            @if ($arrive)
                @php($nextSteps = array_values(array_filter($nextSteps, fn ($step) => $step !== \App\Modules\Reservation\ReservationStatus::Seated)))
            @endif
            @if ($arrive || $nextSteps !== [])
                <div class="row-tools">
                    @if ($arrive)
                        <form method="post" action="{{ route('tenant.admin.reservation.status', $reservation) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ \App\Modules\Reservation\ReservationStatus::Seated->value }}">
                            <button class="btn btn-sm arrive-btn" type="submit">{{ __('reservation.status.seated') }}</button>
                        </form>
                    @endif
                    @if ($nextSteps !== [])
                    @component('partials.kebab', ['label' => __('reservation.actions_for', ['name' => $reservation->customer->name])])
                        @foreach ($nextSteps as $next)
                            <form method="post" action="{{ route('tenant.admin.reservation.status', $reservation) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $next->value }}">
                                <button type="submit">{{ $next->actionLabel() }}</button>
                            </form>
                        @endforeach
                    @endcomponent
                    @endif
                </div>
            @endif
        </li>
    @endforeach
</ul>
