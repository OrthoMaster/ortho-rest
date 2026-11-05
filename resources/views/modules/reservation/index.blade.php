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
        <div class="date-tools">
            @if ($showAll)
                <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['all' => null, 'page' => null]) }}">{{ __('reservation.show_by_day') }}</a>
            @else
                @include('partials.date-nav', ['date' => $date])
                <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['all' => 1, 'date' => null, 'page' => null]) }}">{{ __('reservation.show_all') }}</a>
            @endif
        </div>
        <form method="get" class="inline-form">
            @if ($showAll)
                <input type="hidden" name="all" value="1">
            @else
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            @endif
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

    @if ($showAll)
        <p class="lead">{{ __('reservation.all_summary', ['count' => $reservations->total()]) }}</p>
    @else
        <p class="lead">{{ __('reservation.day_summary', ['count' => $reservations->count(), 'guests' => $guests]) }}</p>
    @endif

    @if ($reservations->isEmpty())
        <p class="empty">{{ __('reservation.empty') }}</p>
    @elseif ($showAll)
        @foreach ($reservations->getCollection()->groupBy(fn ($reservation) => $reservation->reserved_at->toDateString()) as $day => $rows)
            <section class="day-group">
                <h2>{{ day_label(\Carbon\CarbonImmutable::parse($day)) }}</h2>
                @include('modules.reservation.rows', ['rows' => $rows])
            </section>
        @endforeach
        @include('partials.pagination', ['paginator' => $reservations])
    @else
        @include('modules.reservation.rows', ['rows' => $reservations])
    @endif
@endsection
