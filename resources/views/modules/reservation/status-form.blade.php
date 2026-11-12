{{-- Một nút đổi trạng thái, luôn hỏi lại trước khi gửi để tránh bấm nhầm. --}}
@php($danger = in_array($next, [\App\Modules\Reservation\ReservationStatus::Rejected, \App\Modules\Reservation\ReservationStatus::Cancelled, \App\Modules\Reservation\ReservationStatus::NoShow], true))
@php($summary = __('reservation.confirm_status', ['name' => $reservation->customer->name, 'time' => $reservation->reserved_at->format('H:i d/m'), 'party' => $reservation->party_size, 'status' => $next->label()]))
@if ($next === \App\Modules\Reservation\ReservationStatus::Seated && \App\Modules\DineIn\Tables::canSeat(auth()->user()))
    {{-- Khách đến: hộp thoại chung (seat-dialog) hỏi lại và cho chọn bàn luôn. --}}
    <button @class([$class ?? null]) type="button" data-seat="{{ route('tenant.admin.reservation.status', $reservation) }}"
            data-seat-text="{{ $summary }}" data-seat-area="{{ $reservation->area?->code }}">{{ $slot ?? $next->actionLabel() }}</button>
@else
    <form method="post" action="{{ route('tenant.admin.reservation.status', $reservation) }}"
          data-confirm="{{ $summary }}"
          data-confirm-title="{{ $next->actionLabel() }}?" data-confirm-ok="{{ $next->actionLabel() }}" @if ($danger) data-confirm-danger @endif>
        @csrf
        <input type="hidden" name="status" value="{{ $next->value }}">
        <button @class(array_filter([$class ?? null, ($danger && ($menu ?? false)) ? 'is-danger' : null])) type="submit">{{ $slot ?? $next->actionLabel() }}</button>
    </form>
@endif
