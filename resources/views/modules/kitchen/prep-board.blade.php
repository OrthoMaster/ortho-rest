{{--
    Đơn mang đi chưa giao, theo giờ giao. Mỗi món cho biết bếp, bar đã xong chưa.
    Đủ món thì Đã đóng gói (đơn Sẵn sàng); khách đến nhận thì Đã giao khách.
    data-line-id: đơn mới và món vừa xong làm màn hình kêu.
--}}
<div class="prep-layout">
<div class="stack">
@if ($orders->isEmpty())
    <p class="empty">{{ __('kitchen.prep_empty') }}</p>
@else
    <div class="tickets">
        @foreach ($orders as $order)
            @php($ready = $order->status === \App\Modules\Takeaway\OrderStatus::Ready)
            {{-- Chỉ món qua màn hình khu mới có trạng thái; món không qua khu đã xong ngay khi gọi. --}}
            @php($tracked = $order->items->whereNotNull('dinein_station_id'))
            @php($done = $tracked->whereNotNull('done_at'))
            @php($missing = $tracked->count() - $done->count())
            @php($range = ['code' => $order->code()])
            <section @class(['ticket', 'is-takeaway', 'is-packed' => $ready, 'is-late' => $order->pickup_at->isPast() && ! $ready]) data-ticket data-line-id="o{{ $order->id }}">
                <div class="ticket-head">
                    <span class="ticket-title">
                        <strong class="ticket-table">{{ $order->code() }}</strong>
                        <span @class(['badge', $order->status->badge()])>{{ $order->status->label() }}</span>
                    </span>
                    @component('partials.kebab', ['label' => __('kitchen.order_actions', $range)])
                        <button type="button" data-show-order="{{ $order->id }}">{{ __('kitchen.view_order') }}</button>
                        @if ($order->customer->phone)
                            <a href="tel:{{ $order->customer->phone }}">{{ __('kitchen.call_customer') }}</a>
                        @endif
                        <form method="post" action="{{ route('tenant.admin.kitchen.takeaway.status', $order) }}" data-confirm-danger
                              data-confirm-title="{{ __('kitchen.cancel_order_title', $range) }}" data-confirm-ok="{{ __('kitchen.cancel_order') }}" data-confirm="{{ __('kitchen.cancel_order_confirm') }}">
                            @csrf
                            <input type="hidden" name="status" value="cancelled">
                            <button class="is-danger" type="submit">{{ __('kitchen.cancel_order') }}</button>
                        </form>
                    @endcomponent
                </div>
                <div class="ticket-sub">
                    @include('modules.kitchen.pickup-time')
                </div>
                <template data-order-detail="{{ $order->id }}" data-title="{{ __('kitchen.order_title', $range) }}">@include('modules.kitchen.prep-order')</template>
                <p class="ticket-customer">{{ $order->customer->name }}@if ($order->customer->phone) · {{ mask_phone($order->customer->phone) }}@endif</p>
                @if ($order->notes)<p class="dine-note ticket-note">{{ $order->notes }}</p>@endif
                <ul class="ticket-lines">
                    @foreach ($order->items as $item)
                        <li @if ($item->done_at && $item->dinein_station_id) data-line-id="d{{ $item->id }}" @endif>
                            <span class="ticket-qty">{{ $item->quantity }}</span>
                            <span class="ticket-name">
                                {{ $item->name }}
                                @foreach ($item->optionLines() as $optionLine)<small>{{ $optionLine }}</small>@endforeach
                                @if ($item->note)<small class="dine-note">{{ $item->note }}</small>@endif
                            </span>
                            @if ($item->dinein_station_id)
                                <span @class(['badge', $item->done_at ? 'badge-ok' : 'badge-muted'])>{{ $item->done_at ? __('kitchen.item_done') : __('kitchen.item_cooking') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="ticket-foot">
                    <small class="row-note">@if ($tracked->isNotEmpty()){{ __('kitchen.items_done', ['done' => $done->count(), 'total' => $tracked->count()]) }}@endif</small>
                    <span class="ticket-actions">
                        @if ($ready)
                            <form method="post" action="{{ route('tenant.admin.kitchen.takeaway.status', $order) }}"
                                  data-confirm-title="{{ __('kitchen.deliver_title', $range) }}" data-confirm-ok="{{ __('kitchen.deliver') }}" data-confirm="{{ __('kitchen.deliver_confirm') }}">
                                @csrf
                                <input type="hidden" name="status" value="delivered">
                                <button class="btn btn-sm" type="submit">{{ __('kitchen.deliver') }}</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('tenant.admin.kitchen.takeaway.status', $order) }}"
                                  data-confirm-title="{{ __('kitchen.pack_title', $range) }}" data-confirm-ok="{{ __('kitchen.pack') }}"
                                  data-confirm="{{ $missing > 0 ? __('kitchen.pack_confirm_missing', ['count' => $missing]) : __('kitchen.pack_confirm') }}">
                                @csrf
                                <input type="hidden" name="status" value="ready">
                                <button @class(['btn', 'btn-sm', 'btn-quiet' => $missing > 0]) type="submit">{{ __('kitchen.pack') }}</button>
                            </form>
                        @endif
                    </span>
                </div>
            </section>
        @endforeach
    </div>
@endif
</div>

{{-- Cột phải: đơn đã giao trong ca, bấm để xem. --}}
<section class="panel prep-delivered">
    <h2>{{ $shiftStart ? __('kitchen.delivered_shift') : __('kitchen.delivered_today') }}</h2>
    @if ($delivered->isEmpty())
        <p class="row-note">{{ __('kitchen.delivered_none') }}</p>
    @else
        <ul class="rows">
            @foreach ($delivered as $order)
                <li class="row-link">
                    <button class="row-main" type="button" data-show-order="{{ $order->id }}">
                        <span class="row-name">{{ $order->code() }} · {{ $order->customer->name }}<small>{{ trans_choice('takeaway.item_count', $order->items->sum('quantity'), ['count' => $order->items->sum('quantity')]) }} · {{ vnd($order->total) }}</small></span>
                        <small class="row-note">{{ __('kitchen.delivered_at', ['time' => $order->updated_at->format('H:i')]) }}</small>
                    </button>
                    <template data-order-detail="{{ $order->id }}" data-title="{{ __('kitchen.order_title', ['code' => $order->code()]) }}">@include('modules.kitchen.prep-order')</template>
                </li>
            @endforeach
        </ul>
    @endif
</section>
</div>
