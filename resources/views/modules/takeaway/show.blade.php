@extends('layouts.admin')

@section('title', __('takeaway.number', ['number' => $order->number]))

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ __('takeaway.number', ['number' => $order->number]) }}</h1>
            <span class="badge {{ $order->status->badge() }}">{{ $order->status->label() }}</span>
        </div>
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
            <li>
                <span class="row-name">{{ __('takeaway.total') }}</span>
                <strong>{{ vnd($order->total) }}</strong>
            </li>
        </ul>
    </section>
@endsection
