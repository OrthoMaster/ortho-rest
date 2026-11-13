@extends('layouts.admin')

@section('title', __('takeaway.number', ['code' => $order->code()]))

@section('content')
    <div class="page-head">
        @component('partials.page-title', ['title' => __('takeaway.number', ['code' => $order->code()]), 'back' => route('tenant.admin.takeaway.index', ['date' => $order->pickup_at->toDateString()])])
            <span class="badge {{ $order->status->badge() }}">{{ $order->status->label() }}</span>
        @endcomponent
        @if ($order->isEditable() && auth()->user()->can('takeaway.create'))
            <a class="btn btn-quiet" href="{{ route('tenant.admin.takeaway.edit', $order) }}">{{ __('takeaway.edit') }}</a>
        @endif
    </div>

    @error('status')<p class="flash flash-error">{{ $message }}</p>@enderror

    @if ($order->status->next() && auth()->user()->can('takeaway.status'))
        <section class="panel">
            @include('modules.takeaway.status-actions')
        </section>
    @endif

    <section class="panel">
        <dl class="details">
            <dt>{{ __('reservation.customer_name') }}</dt>
            <dd>{{ $order->customer->name }}</dd>
            @if ($order->customer->phone)
                <dt>{{ __('reservation.customer_phone') }}</dt>
                <dd><a href="tel:{{ $order->customer->phone }}">{{ $order->customer->phone }}</a></dd>
            @endif
            <dt>{{ __('takeaway.pickup_at') }}</dt>
            <dd>{{ $order->pickup_at->format('H:i d/m/Y') }}</dd>
            <dt>{{ __('takeaway.payment') }}</dt>
            <dd>{{ __('takeaway.pay_on_pickup') }}</dd>
            @if ($order->status === \App\Modules\Takeaway\OrderStatus::Delivered)
                <dt>{{ __('dinein.bill_number') }}</dt>
                <dd class="bill-number-cell">
                    {{ $order->bill_number ?? __('dinein.bills_no_number') }}
                    @can('takeaway.status')
                        <button class="btn btn-quiet btn-sm" type="button" data-open-dialog="bill-number-dialog">{{ $order->bill_number ? __('dinein.bills_edit') : __('dinein.bills_add') }}</button>
                    @endcan
                </dd>
            @endif
            <dt>{{ __('reservation.created_by') }}</dt>
            <dd>{{ $order->creator?->name ?? __('reservation.created_by_customer') }}</dd>
            @if ($order->notes)
                <dt>{{ __('reservation.notes') }}</dt>
                <dd>{{ $order->notes }}</dd>
            @endif
        </dl>
    </section>

    <section class="panel">
        <h2>{{ __('takeaway.items') }}</h2>
        <ul class="rows dine-lines">
            @foreach ($order->items as $item)
                <li>
                    <span class="row-name">{{ $item->quantity }} × {{ $item->name }}@foreach ($item->optionLines() as $optionLine)<small>{{ $optionLine }}</small>@endforeach @if ($item->note)<small class="dine-note">{{ $item->note }}</small>@endif</span>
                    <span>{{ vnd($item->lineTotal()) }}</span>
                </li>
            @endforeach
            @if ($order->discount_amount > 0)
                <li>
                    <span class="row-name">{{ __('takeaway.subtotal') }}</span>
                    <span>{{ vnd($order->subtotal) }}</span>
                </li>
                <li>
                    <span class="row-name">{{ __('takeaway.discount') }}<small>{{ collect([$order->discount_code, $order->discount_type->label($order->discount_value)])->filter()->join(', ') }}</small></span>
                    <span>−{{ vnd($order->discount_amount) }}</span>
                </li>
            @endif
            <li>
                <span class="row-name">{{ __('takeaway.total') }}</span>
                <strong>{{ vnd($order->total) }}</strong>
            </li>
        </ul>
    </section>

    @if ($order->edits->isNotEmpty())
        <section class="panel">
            <h2>{{ __('takeaway.edit_history') }}</h2>
            <ul class="rows">
                @foreach ($order->edits as $edit)
                    <li>
                        <span class="row-name">
                            @foreach ($edit->changes as $change)
                                <span class="edit-line">{{ $change }}</span>
                            @endforeach
                            <small>{{ $edit->user?->name }}, {{ $edit->created_at->format('H:i d/m/Y') }}</small>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
    @if ($order->status === \App\Modules\Takeaway\OrderStatus::Delivered)
        @can('takeaway.status')
            @php($billErrors = $errors->getBag('bill-number'))
            <dialog id="bill-number-dialog" class="confirm-dialog" aria-labelledby="bill-number-title" @if ($billErrors->any()) data-auto-open @endif>
                <form method="post" action="{{ route('tenant.admin.takeaway.bill-number', $order) }}" class="stack" data-guard>
                    @csrf
                    @method('put')
                    <h2 id="bill-number-title">{{ $order->bill_number ? __('dinein.bills_edit') : __('dinein.bills_add') }}</h2>
                    <div class="field">
                        <label for="bill-number-input">{{ __('dinein.bill_number') }}</label>
                        <input id="bill-number-input" name="bill_number" value="{{ $order->bill_number }}" maxlength="40" autocomplete="off" placeholder="{{ __('dinein.bill_number_example') }}">
                        @if ($billErrors->has('bill_number'))<small class="error">{{ $billErrors->first('bill_number') }}</small>@endif
                    </div>
                    <div class="dialog-foot">
                        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                        <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
                    </div>
                </form>
            </dialog>
        @endcan
    @endif
@endsection
