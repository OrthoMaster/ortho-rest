{{-- Một phiếu trong danh sách thu ngân. $bill, $waiting: ở hàng Chờ thu tiền (có phiếu mới thì kêu). --}}
<li class="row-link" @if ($waiting ?? false) data-line-id="{{ $bill->key() }}" @endif>
    <span class="row-name">
        <button class="row-open" type="button" data-open-dialog="{{ $bill->paidAt() ? 'number' : 'cash' }}-{{ $bill->key() }}">
            {{ $bill->title() }}@if ($bill->customer())<span class="row-note"> · {{ $bill->customer() }}</span>@endif
            @if ($bill->billNumber())
                · <strong>{{ __('cashier.number', ['number' => $bill->billNumber()]) }}</strong>
            @endif
        </button>
        <small>{{ $bill->paidAt() ? __('cashier.paid_at', ['time' => $bill->paidAt()->format('H:i'), 'name' => $bill->payer() ?? '—']) : $bill->state() }}</small>
    </span>
    @if ($bill->paidAt() && ! $bill->billNumber())
        <span class="badge badge-warn">{{ __('cashier.no_number') }}</span>
    @endif
    <span class="cash-amount">
        <strong>{{ vnd($bill->total()) }}</strong>
        @if ($bill->discount() > 0)<small>−{{ vnd($bill->discount()) }}</small>@endif
    </span>
    @if (! $bill->paidAt())
        @can('cashier.collect')
            <button class="btn btn-sm" type="button" data-open-dialog="cash-{{ $bill->key() }}">{{ __('cashier.collect') }}</button>
        @endcan
    @else
        @component('partials.kebab', ['label' => __('cashier.actions', ['bill' => $bill->title()])])
            <button type="button" data-open-dialog="number-{{ $bill->key() }}">{{ $bill->billNumber() ? __('cashier.number_edit') : __('cashier.number_add') }}</button>
        @endcomponent
    @endif
</li>
