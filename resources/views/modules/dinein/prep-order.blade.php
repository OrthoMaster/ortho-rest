{{-- Chi tiết đơn mang đi trong popup của Chuẩn bị đơn mang đi (chép từ template của từng đơn). --}}
<dl class="details">
    <dt>{{ __('reservation.customer_name') }}</dt>
    <dd>{{ $order->customer->name }}</dd>
    @if ($order->customer->phone)
        <dt>{{ __('reservation.customer_phone') }}</dt>
        <dd><a href="tel:{{ $order->customer->phone }}">{{ $order->customer->phone }}</a></dd>
    @endif
    <dt>{{ __('takeaway.pickup_at') }}</dt>
    <dd>{{ $order->pickup_at->format('H:i d/m/Y') }}</dd>
    <dt>{{ __('takeaway.status_label') }}</dt>
    <dd><span @class(['badge', $order->status->badge()])>{{ $order->status->label() }}</span></dd>
    <dt>{{ __('takeaway.payment') }}</dt>
    <dd>{{ __('takeaway.pay_on_pickup') }}</dd>
    @if ($order->notes)
        <dt>{{ __('reservation.notes') }}</dt>
        <dd>{{ $order->notes }}</dd>
    @endif
</dl>
<ul class="rows dine-lines">
    @foreach ($order->items as $item)
        <li>
            <span class="row-name">{{ $item->quantity }} × {{ $item->name }}@foreach ($item->optionLines() as $optionLine)<small>{{ $optionLine }}</small>@endforeach @if ($item->note)<small class="dine-note">{{ $item->note }}</small>@endif</span>
            <span class="line-total">{{ vnd($item->lineTotal()) }}</span>
        </li>
    @endforeach
    @if ($order->discount_amount > 0)
        <li>
            <span class="row-name">{{ __('takeaway.discount') }}<small>{{ collect([$order->discount_code, $order->discount_type?->label($order->discount_value)])->filter()->join(', ') }}</small></span>
            <span class="line-total">−{{ vnd($order->discount_amount) }}</span>
        </li>
    @endif
    <li>
        <span class="row-name">{{ __('takeaway.total') }}</span>
        <strong class="line-total">{{ vnd($order->total) }}</strong>
    </li>
</ul>
