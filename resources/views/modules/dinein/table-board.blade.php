{{--
    Một bàn đang có khách. Máy tính: cột trái các lượt gọi món, tạm tính và nút;
    cột phải khách và sự cố. Điện thoại xếp một cột.
--}}
<div class="table-layout">
<div class="stack">
@foreach ($rounds as $round => $lines)
    <section class="panel">
        <div class="panel-head">
            <h2>{{ __('dinein.round', ['round' => $round]) }}</h2>
            <small class="row-note">{{ __('dinein.round_meta', ['time' => $lines->first()->created_at->format('H:i'), 'name' => $lines->first()->orderedBy?->name ?? '—']) }}</small>
        </div>
        <ul class="rows dine-lines">
            @foreach ($lines as $line)
                @include('modules.dinein.line')
            @endforeach
        </ul>
    </section>
@endforeach

<section class="panel dine-bill">
    <div class="panel-head">
        <div>
            <h2>{{ __('dinein.subtotal') }}</h2>
            <small class="row-note">{{ __('dinein.subtotal_hint') }}</small>
        </div>
        <strong class="dine-total">{{ vnd($table->subtotal()) }}</strong>
    </div>
    @if ($table->discount_type)
        @include('modules.dinein.bill-discount')
    @endif
</section>

<div class="dine-actions">
    <a class="btn" href="{{ route('tenant.admin.dinein.tables.order', $label) }}">{{ __('dinein.order_more') }}</a>
    @can('dinein.close')
        <span class="dine-actions-end">
            <button class="btn btn-quiet" type="button" data-open-dialog="bill-dialog">{{ __('dinein.bill') }}</button>
            <button class="btn btn-quiet" type="button" data-open-dialog="close-dialog">{{ __('dinein.close') }}</button>
        </span>

        {{-- Đóng bàn: hỏi lại, kèm ô số hoá đơn của máy tính tiền (không bắt buộc) để đối soát doanh thu. --}}
        <dialog id="close-dialog" class="confirm-dialog" aria-labelledby="close-title">
            <form method="post" action="{{ route('tenant.admin.dinein.tables.close', $table) }}" class="stack">
                @csrf
                <h2 id="close-title">{{ __('dinein.close_title', ['table' => $label]) }}</h2>
                <p>{{ __('dinein.close_confirm', ['total' => vnd($table->total())]) }}</p>
                <div class="field">
                    <label for="close-bill-number">{{ __('dinein.bill_number') }}</label>
                    <input id="close-bill-number" name="bill_number" maxlength="40" autocomplete="off" placeholder="{{ __('dinein.bill_number_example') }}">
                    <small class="hint">{{ __('dinein.bill_number_hint') }}</small>
                </div>
                <div class="dialog-foot">
                    <button class="btn" type="submit">{{ __('dinein.close') }}</button>
                    <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
                </div>
            </form>
        </dialog>

        {{-- Phiếu tạm tính: mọi món gộp lại (không theo lượt), để nhập vào máy tính tiền. --}}
        <dialog id="bill-dialog" class="dine-dialog bill-dialog" aria-labelledby="bill-title" data-backdrop-close>
            <div class="stack">
                <div class="panel-head dialog-head">
                    <div>
                        <h2 id="bill-title">{{ __('dinein.bill_title', ['table' => $label]) }}</h2>
                        <small class="row-note">{{ __('dinein.bill_meta', ['opened' => $table->created_at->format('H:i d/m'), 'now' => now()->format('H:i')]) }}</small>
                    </div>
                    <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
                </div>
                @php($bill = $table->billLines())
                @if ($bill->isEmpty())
                    <p class="row-note">{{ __('dinein.bill_empty') }}</p>
                @else
                    <ul class="rows bill-lines">
                        @foreach ($bill as $row)
                            <li>
                                <span class="bill-qty">{{ $row['quantity'] }}</span>
                                <span class="row-name">{{ $row['name'] }}@foreach ($row['options'] as $optionLine)<small>{{ $optionLine }}</small>@endforeach<small>{{ vnd($row['unit']) }}</small></span>
                                <strong class="line-total">{{ vnd($row['total']) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="bill-total">
                    <span>{{ __('dinein.bill_count', ['count' => $bill->sum('quantity')]) }}</span>
                    <strong>{{ vnd($table->subtotal()) }}</strong>
                </div>
                @if ($table->discount_type)
                    @include('modules.dinein.bill-discount', ['inBill' => true])
                @endif
                <small class="row-note">{{ __('dinein.subtotal_hint') }}</small>
            </div>
        </dialog>
    @endcan
</div>
</div>

<div class="stack">
    @include('modules.dinein.table-guest')
</div>
</div>
