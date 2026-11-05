@extends('layouts.admin')

@section('title', __('modules.takeaway'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.takeaway') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.takeaway.create') }}">{{ __('takeaway.add') }}</a>
    </div>

    @include('modules.takeaway.subnav')
    @error('status')<p class="flash flash-error">{{ $message }}</p>@enderror

    <div class="filters">
        @include('partials.date-nav', ['date' => $date])
        <form method="get" class="inline-form">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <select name="status" aria-label="{{ __('reservation.filter_status') }}" onchange="this.form.submit()">
                <option value="">{{ __('reservation.all_statuses') }}</option>
                @foreach (\App\Modules\Takeaway\OrderStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
            <noscript><button class="btn btn-quiet" type="submit">{{ __('ui.super.search_submit') }}</button></noscript>
        </form>
    </div>

    @if ($orders->isEmpty())
        <p class="empty">{{ __('takeaway.empty') }}</p>
    @else
        <ul class="booking-list">
            @foreach ($orders as $order)
                <li>
                    <a class="booking-time" href="{{ route('tenant.admin.takeaway.show', $order) }}">{{ $order->pickup_at->format('H:i') }}</a>
                    <div class="booking-main">
                        <a href="{{ route('tenant.admin.takeaway.show', $order) }}">{{ __('takeaway.number', ['number' => $order->number]) }}, {{ $order->customer->name }}</a>
                        <small>{{ $order->items->map(fn ($item) => $item->quantity.' × '.$item->name)->join(', ') }}</small>
                        <small>{{ vnd($order->total) }}</small>
                    </div>
                    <span class="badge {{ $order->status->badge() }}">{{ $order->status->label() }}</span>
                    @include('modules.takeaway.status-actions')
                </li>
            @endforeach
        </ul>
    @endif
@endsection
