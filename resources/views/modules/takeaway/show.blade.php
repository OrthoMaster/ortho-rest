@extends('layouts.admin')

@section('title', __('takeaway.number', ['number' => $order->number]))

@section('content')
    <div class="page-head">
        @component('partials.page-title', ['title' => __('takeaway.number', ['number' => $order->number]), 'back' => route('tenant.admin.takeaway.index', ['date' => $order->pickup_at->toDateString()])])
            <span class="badge {{ $order->status->badge() }}">{{ $order->status->label() }}</span>
        @endcomponent
    </div>

    @error('status')<p class="flash flash-error">{{ $message }}</p>@enderror

    @if ($order->status->next())
        <section class="panel">
            @include('modules.takeaway.status-actions')
        </section>
    @endif

    <section class="panel">
        <dl class="details">
            <dt>{{ __('reservation.customer_name') }}</dt>
            <dd>{{ $order->customer->name }}</dd>
            <dt>{{ __('reservation.customer_phone') }}</dt>
            <dd><a href="tel:{{ $order->customer->phone }}">{{ $order->customer->phone }}</a></dd>
            <dt>{{ __('takeaway.pickup_at') }}</dt>
            <dd>{{ $order->pickup_at->format('H:i d/m/Y') }}</dd>
            <dt>{{ __('takeaway.payment') }}</dt>
            <dd>{{ __('takeaway.pay_on_pickup') }}</dd>
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
        <ul class="rows">
            @foreach ($order->items as $item)
                <li>
                    <span class="row-name">{{ $item->quantity }} × {{ $item->name }}</span>
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
@endsection
