{{-- Giờ giao của đơn mang đi và còn bao lâu (trễ thì ghi trễ bao lâu). --}}
@php($left = (int) now()->startOfMinute()->diffInMinutes($order->pickup_at->toImmutable()->startOfMinute(), false))
<span @class(['row-note', 'ticket-time', 'is-late' => $left < 0])>
    @if ($left > 0)
        {{ __('kitchen.pickup_left', ['time' => $order->pickup_at->format('H:i'), 'left' => \App\Modules\Kitchen\Waiting::text($left)]) }}
    @elseif ($left === 0)
        {{ __('kitchen.pickup_now', ['time' => $order->pickup_at->format('H:i')]) }}
    @else
        {{ __('kitchen.pickup_late', ['time' => $order->pickup_at->format('H:i'), 'left' => \App\Modules\Kitchen\Waiting::text($left)]) }}
    @endif
</span>
