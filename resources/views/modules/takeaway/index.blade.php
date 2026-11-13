@extends('layouts.admin')

@section('title', __('modules.takeaway'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.takeaway') }}</h1>
        @can('takeaway.create')
            <a class="btn" href="{{ route('tenant.admin.takeaway.create') }}">{{ __('takeaway.add') }}</a>
        @endcan
    </div>

    @include('modules.takeaway.subnav')
    @error('status')<p class="flash flash-error">{{ $message }}</p>@enderror

    @php($keep = ['date' => $date->toDateString()])
    @php($statusOptions = collect(\App\Modules\Takeaway\OrderStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())
    @php($pills = filter_pills('status', $statusOptions, $statuses))
    @component('partials.filter-bar', ['id' => 'takeaway', 'keep' => $keep, 'pills' => $pills, 'clearUrl' => url()->current().'?'.http_build_query($keep)])
        @slot('date')
            @include('partials.date-nav', ['date' => $date])
        @endslot
        @include('partials.filter-multi', ['name' => 'status', 'label' => __('reservation.filter_status'), 'all' => __('reservation.all_statuses'), 'options' => $statusOptions, 'values' => $statuses])
    @endcomponent

    @if ($orders->isEmpty())
        <p class="empty">{{ __('takeaway.empty') }}</p>
    @else
        <ul class="booking-list">
            @foreach ($orders as $order)
                <li>
                    <a class="booking-time" href="{{ route('tenant.admin.takeaway.show', $order) }}">{{ $order->pickup_at->format('H:i') }}</a>
                    <div class="booking-main">
                        <a href="{{ route('tenant.admin.takeaway.show', $order) }}">{{ __('takeaway.number', ['code' => $order->code()]) }}, {{ $order->customer->name }}</a>
                        <small>{{ $order->items->map(fn ($item) => $item->quantity.' × '.$item->name)->join(', ') }}</small>
                        <small>{{ vnd($order->total) }}{{ $order->bill_number ? ' · '.__('cashier.number', ['number' => $order->bill_number]) : '' }}</small>
                    </div>
                    @if ($order->edits_count > 0)<span class="badge badge-warn">{{ __('takeaway.edited') }}</span>@endif
                    <span class="badge {{ $order->status->badge() }}">{{ $order->status->label() }}</span>
                    @include('modules.takeaway.status-actions')
                </li>
            @endforeach
        </ul>
    @endif
@endsection
