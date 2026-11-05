@extends('layouts.admin')

@section('title', __('modules.reservation'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.reservation') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.reservation.create') }}">{{ __('reservation.add') }}</a>
    </div>

    @include('modules.reservation.subnav')
    @error('status')<p class="flash flash-error">{{ $message }}</p>@enderror

    <div class="filters">
        @include('partials.date-nav', ['date' => $date])
        <form method="get" class="inline-form">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <select name="status" aria-label="{{ __('reservation.filter_status') }}" onchange="this.form.submit()">
                <option value="">{{ __('reservation.all_statuses') }}</option>
                @foreach (\App\Modules\Reservation\ReservationStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
            <select name="area" aria-label="{{ __('reservation.filter_area') }}" onchange="this.form.submit()">
                <option value="">{{ __('reservation.all_areas') }}</option>
                @foreach ($areas as $area)
                    <option value="{{ $area->id }}" @selected($areaId === $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>
            <noscript><button class="btn btn-quiet" type="submit">{{ __('ui.super.search_submit') }}</button></noscript>
        </form>
    </div>

    <p class="lead">{{ __('reservation.day_summary', ['count' => $reservations->count(), 'guests' => $guests]) }}</p>

    @if ($reservations->isEmpty())
        <p class="empty">{{ __('reservation.empty') }}</p>
    @else
        <ul class="booking-list">
            @foreach ($reservations as $reservation)
                <li>
                    <a class="booking-time" href="{{ route('tenant.admin.reservation.show', $reservation) }}">{{ $reservation->reserved_at->format('H:i') }}</a>
                    <div class="booking-main">
                        <a href="{{ route('tenant.admin.reservation.show', $reservation) }}">{{ $reservation->customer->name }}</a>
                        <small>{{ __('reservation.party', ['count' => $reservation->party_size]) }}, {{ $reservation->area->name }}</small>
                        <small><a href="tel:{{ $reservation->customer->phone }}">{{ $reservation->customer->phone }}</a></small>
                    </div>
                    <span class="badge {{ $reservation->status->badge() }}">{{ $reservation->status->label() }}</span>
                    @include('modules.reservation.status-actions')
                </li>
            @endforeach
        </ul>
    @endif
@endsection
