@use('App\Modules\Kitchen\Screens')
{{--
    Phiếu của khu: mỗi bàn một phiếu (gộp các lần gọi), mỗi đơn mang đi một phiếu (màu khác).
    Mỗi món một dòng, có nút Xong riêng; món khác tuỳ chọn là dòng riêng.
--}}
{{-- Đơn đã xong trong ca: nút mở danh sách (không chiếm chỗ trên màn hình). --}}
<div class="board-bar">
    <button class="btn btn-quiet btn-sm" type="button" data-open-dialog="finished-dialog">{{ __('kitchen.finished') }}@if ($finished->isNotEmpty()) ({{ $finished->count() }})@endif</button>
</div>
<dialog id="finished-dialog" class="dine-dialog" aria-labelledby="finished-title" data-backdrop-close>
    <div class="stack">
        <div class="panel-head dialog-head">
            <h2 id="finished-title">{{ Screens::shiftStart() ? __('kitchen.finished_shift') : __('kitchen.finished_today') }}</h2>
            <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
        </div>
        @if ($finished->isEmpty())
            <p class="row-note">{{ __('kitchen.finished_none') }}</p>
        @else
            <ul class="rows dine-lines">
                @foreach ($finished as $row)
                    <li>
                        <span class="row-name">{{ $row['where'] }} · {{ $row['line']->quantity }} × {{ $row['line']->name }}@foreach ($row['line']->optionLines() as $optionLine)<small>{{ $optionLine }}</small>@endforeach</span>
                        <small class="row-note">{{ $row['at']->format('H:i') }}</small>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</dialog>

@if ($tickets->isEmpty())
    <p class="empty">{{ __('kitchen.board_empty') }}</p>
@else
    <div class="tickets">
        @foreach ($tickets as $ticket)
            @php($lines = $ticket['lines'])
            @php($first = $lines->first())
            @php($takeaway = $ticket['type'] === 'takeaway')
            @php($field = $takeaway ? 'takeaway[]' : 'lines[]')
            @if ($takeaway)
                @php($order = $first->order)
                @php($left = (int) now()->startOfMinute()->diffInMinutes($order->pickup_at->toImmutable()->startOfMinute(), false))
                @php($late = $left < 0)
                @php($label = __('kitchen.takeaway_tag').' '.$order->code())
            @else
                @php($minutes = (int) $ticket['at']->diffInMinutes(now()))
                @php($late = $minutes >= 15)
                @php($label = $first->diningTable->label)
            @endif
            <section @class(['ticket', 'is-takeaway' => $takeaway, 'is-late' => $late]) data-ticket>
                <div class="ticket-head">
                    @if ($takeaway)
                        <span class="ticket-title">
                            <small class="badge ticket-tag">{{ __('kitchen.takeaway_tag') }}</small>
                            <strong class="ticket-table">{{ $order->code() }}</strong>
                        </span>
                        @include('modules.kitchen.pickup-time')
                    @else
                        <strong class="ticket-table">{{ $label }}</strong>
                        <span class="row-note ticket-time">{{ $minutes < 1 ? __('kitchen.ordered_now') : __('kitchen.ordered_ago', ['time' => \App\Modules\Kitchen\Waiting::text($minutes)]) }}</span>
                    @endif
                </div>
                <ul class="ticket-lines">
                    @foreach ($lines as $line)
                        <li data-line-id="{{ $takeaway ? 't' : '' }}{{ $line->id }}">
                            <span class="ticket-qty">{{ $line->quantity }}</span>
                            <span class="ticket-name">
                                {{ $line->name }}
                                @foreach ($line->optionLines() as $optionLine)<small>{{ $optionLine }}</small>@endforeach
                                @if ($line->note)<small class="dine-note">{{ $line->note }}</small>@endif
                            </span>
                            <form method="post" action="{{ route('tenant.admin.kitchen.done', $station) }}"
                                  data-confirm="{{ $takeaway ? __('kitchen.done_takeaway_confirm', ['name' => $line->quantity.' × '.$line->name, 'code' => $order->code()]) : __('kitchen.done_confirm', ['name' => $line->quantity.' × '.$line->name]) }}" data-confirm-ok="{{ __('kitchen.done') }}">
                                @csrf
                                <input type="hidden" name="{{ $field }}" value="{{ $line->id }}">
                                <button class="btn btn-sm" type="submit">{{ __('kitchen.done') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <div class="ticket-foot">
                    <small class="row-note">{{ $takeaway ? $order->customer->name : __('kitchen.ordered_by', ['name' => $lines->pluck('orderedBy.name')->filter()->unique()->implode(', ') ?: '—']) }}</small>
                    @if ($lines->count() > 1)
                        <form method="post" action="{{ route('tenant.admin.kitchen.done', $station) }}"
                              data-confirm="{{ $takeaway ? __('kitchen.done_order_confirm', ['code' => $order->code()]) : __('kitchen.done_all_confirm', ['table' => $label]) }}" data-confirm-ok="{{ __('kitchen.done_all') }}">
                            @csrf
                            @foreach ($lines as $line)
                                <input type="hidden" name="{{ $field }}" value="{{ $line->id }}">
                            @endforeach
                            <button class="btn btn-quiet btn-sm" type="submit">{{ __('kitchen.done_all') }}</button>
                        </form>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
@endif
