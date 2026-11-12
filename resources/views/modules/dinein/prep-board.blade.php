{{--
    Đơn mang đi chưa giao, theo giờ giao. Mỗi món cho biết bếp, bar đã xong chưa.
    Đủ món thì Đã đóng gói (đơn Sẵn sàng); khách đến nhận thì Đã giao khách.
    data-line-id: đơn mới và món vừa xong làm màn hình kêu.
--}}
<div class="prep-layout">
<div class="stack">
@if ($orders->isEmpty())
    <p class="empty">{{ __('dinein.prep_empty') }}</p>
@else
    <div class="tickets">
        @foreach ($orders as $order)
            @php($ready = $order->status === \App\Modules\Takeaway\OrderStatus::Ready)
            @php($done = $order->items->whereNotNull('done_at'))
            @php($missing = $order->items->count() - $done->count())
            @php($range = ['code' => $order->code()])
            <section @class(['ticket', 'is-takeaway', 'is-packed' => $ready, 'is-late' => $order->pickup_at->isPast() && ! $ready]) data-ticket data-line-id="o{{ $order->id }}">
                <div class="ticket-head">
                    <span class="ticket-title">
                        <strong class="ticket-table">{{ $order->code() }}</strong>
                        <span @class(['badge', $order->status->badge()])>{{ $order->status->label() }}</span>
                    </span>
                    @component('partials.kebab', ['label' => __('dinein.order_actions', $range)])
                        <button type="button" data-show-order="{{ $order->id }}">{{ __('dinein.view_order') }}</button>
                        @if ($order->customer->phone)
                            <a href="tel:{{ $order->customer->phone }}">{{ __('dinein.call_customer') }}</a>
                        @endif
                        <form method="post" action="{{ route('tenant.admin.dinein.screens.takeaway.status', $order) }}" data-confirm-danger
                              data-confirm-title="{{ __('dinein.cancel_order_title', $range) }}" data-confirm-ok="{{ __('dinein.cancel_order') }}" data-confirm="{{ __('dinein.cancel_order_confirm') }}">
                            @csrf
                            <input type="hidden" name="status" value="cancelled">
                            <button class="is-danger" type="submit">{{ __('dinein.cancel_order') }}</button>
                        </form>
                    @endcomponent
                </div>
                <div class="ticket-sub">
                    @include('modules.dinein.pickup-time')
                </div>
                <template data-order-detail="{{ $order->id }}" data-title="{{ __('dinein.order_title', $range) }}">@include('modules.dinein.prep-order')</template>
                <p class="ticket-customer">{{ $order->customer->name }}@if ($order->customer->phone) · {{ mask_phone($order->customer->phone) }}@endif</p>
                @if ($order->notes)<p class="dine-note ticket-note">{{ $order->notes }}</p>@endif
                <ul class="ticket-lines">
                    @foreach ($order->items as $item)
                        <li @if ($item->done_at) data-line-id="d{{ $item->id }}" @endif>
                            <span class="ticket-qty">{{ $item->quantity }}</span>
                            <span class="ticket-name">
                                {{ $item->name }}
                                @foreach ($item->optionLines() as $optionLine)<small>{{ $optionLine }}</small>@endforeach
                                @if ($item->note)<small class="dine-note">{{ $item->note }}</small>@endif
                            </span>
                            <span @class(['badge', $item->done_at ? 'badge-ok' : 'badge-muted'])>{{ $item->done_at ? __('dinein.item_done') : __('dinein.item_cooking') }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="ticket-foot">
                    <small class="row-note">{{ __('dinein.items_done', ['done' => $done->count(), 'total' => $order->items->count()]) }}</small>
                    <span class="ticket-actions">
                        @if ($ready)
                            <form method="post" action="{{ route('tenant.admin.dinein.screens.takeaway.status', $order) }}"
                                  data-confirm-title="{{ __('dinein.deliver_title', $range) }}" data-confirm-ok="{{ __('dinein.deliver') }}" data-confirm="{{ __('dinein.deliver_confirm') }}">
                                @csrf
                                <input type="hidden" name="status" value="delivered">
                                <button class="btn btn-sm" type="submit">{{ __('dinein.deliver') }}</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('tenant.admin.dinein.screens.takeaway.status', $order) }}"
                                  data-confirm-title="{{ __('dinein.pack_title', $range) }}" data-confirm-ok="{{ __('dinein.pack') }}"
                                  data-confirm="{{ $missing > 0 ? __('dinein.pack_confirm_missing', ['count' => $missing]) : __('dinein.pack_confirm') }}">
                                @csrf
                                <input type="hidden" name="status" value="ready">
                                <button @class(['btn', 'btn-sm', 'btn-quiet' => $missing > 0]) type="submit">{{ __('dinein.pack') }}</button>
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
    <h2>{{ $shiftStart ? __('dinein.delivered_shift') : __('dinein.delivered_today') }}</h2>
    @if ($delivered->isEmpty())
        <p class="row-note">{{ __('dinein.delivered_none') }}</p>
    @else
        <ul class="rows">
            @foreach ($delivered as $order)
                <li class="row-link">
                    <button class="row-main" type="button" data-show-order="{{ $order->id }}">
                        <span class="row-name">{{ $order->code() }} · {{ $order->customer->name }}<small>{{ trans_choice('takeaway.item_count', $order->items->sum('quantity'), ['count' => $order->items->sum('quantity')]) }} · {{ vnd($order->total) }}</small></span>
                        <small class="row-note">{{ __('dinein.delivered_at', ['time' => $order->updated_at->format('H:i')]) }}</small>
                    </button>
                    <template data-order-detail="{{ $order->id }}" data-title="{{ __('dinein.order_title', ['code' => $order->code()]) }}">@include('modules.dinein.prep-order')</template>
                </li>
            @endforeach
        </ul>
    @endif
</section>
</div>
