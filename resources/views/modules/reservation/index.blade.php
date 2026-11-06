@extends('layouts.admin')

@section('title', __('modules.reservation'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.reservation') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.reservation.create') }}">{{ __('reservation.add') }}</a>
    </div>

    @include('modules.reservation.subnav')
    @error('status')<p class="flash flash-error">{{ $message }}</p>@enderror

    @php($keep = $showAll ? [] : ['date' => $date->toDateString()])
    @php($pills = [])
    @if ($showAll)
        @php($pills[] = ['label' => __('reservation.all_days'), 'url' => request()->fullUrlWithQuery(['all' => null, 'page' => null])])
    @endif
    @if ($status)
        @php($pills[] = ['label' => $status->label(), 'url' => request()->fullUrlWithQuery(['status' => null, 'page' => null])])
    @endif
    @if ($areaId)
        @php($pills[] = ['label' => $areas->firstWhere('id', $areaId)?->name, 'url' => request()->fullUrlWithQuery(['area' => null, 'page' => null])])
    @endif
    @component('partials.filter-bar', ['id' => 'reservation', 'keep' => $keep, 'pills' => $pills, 'clearUrl' => url()->current().'?'.http_build_query($keep)])
        @slot('date')
            @if ($showAll)
                <button class="picker-trigger all-days" type="button" data-filter-open>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/></svg>
                    <span>{{ __('reservation.all_days') }}</span>
                </button>
            @else
                @include('partials.date-nav', ['date' => $date])
            @endif
        @endslot
        <label class="check"><input type="checkbox" name="all" value="1" @checked($showAll)> {{ __('reservation.show_all') }}</label>
        @include('partials.filter-field', ['name' => 'status', 'label' => __('reservation.filter_status'), 'all' => __('reservation.all_statuses'), 'value' => $status?->value,
            'options' => collect(\App\Modules\Reservation\ReservationStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()])
        @include('partials.filter-field', ['name' => 'area', 'label' => __('reservation.filter_area'), 'all' => __('reservation.all_areas'), 'value' => $areaId,
            'options' => $areas->pluck('name', 'id')->all()])
    @endcomponent

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
