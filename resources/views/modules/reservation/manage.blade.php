@extends('layouts.admin')

@section('title', __('reservation.manage'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.reservation') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.reservation.create') }}">{{ __('reservation.add') }}</a>
    </div>

    @include('modules.reservation.subnav')

    @php($keep = $upcoming ? [] : ['upcoming' => 0, 'from' => $from->toDateString(), 'to' => $to->toDateString()])
    @php($statusOptions = collect(\App\Modules\Reservation\ReservationStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())
    @php($areaOptions = $areas->pluck('name', 'id')->all())
    @php($sourceOptions = collect(\App\Modules\Reservation\ReservationSource::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())
    @php($pills = [])
    @if ($search !== '')
        @php($pills[] = ['label' => '“'.$search.'”', 'url' => request()->fullUrlWithQuery(['q' => null, 'page' => null])])
    @endif
    @php($pills = [...$pills, ...filter_pills('status', $statusOptions, $statuses), ...filter_pills('area', $areaOptions, $areaIds), ...filter_pills('source', $sourceOptions, $sources)])

    @component('partials.filter-bar', ['id' => 'reservation-manage', 'keep' => $keep, 'pills' => $pills, 'clearUrl' => url()->current().($keep ? '?'.http_build_query($keep) : '')])
        @slot('date')
            @if ($upcoming)
                <button class="picker-trigger all-days" type="button" data-filter-open>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/></svg>
                    <span>{{ __('reservation.upcoming') }}</span>
                </button>
            @else
                @include('partials.date-range-nav', ['from' => $from, 'to' => $to, 'max' => null, 'maxDays' => \App\Modules\Reservation\Http\ReservationController::MAX_RANGE_DAYS])
            @endif
        @endslot
        <input type="hidden" name="upcoming" value="0">
        <label class="check"><input type="checkbox" name="upcoming" value="1" @checked($upcoming)> {{ __('reservation.upcoming_toggle') }}</label>
        <label class="filter-search">
            <span class="visually-hidden">{{ __('reservation.search') }}</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('reservation.search') }}" autocomplete="off">
        </label>
        @include('partials.filter-multi', ['name' => 'status', 'label' => __('reservation.filter_status'), 'all' => __('reservation.all_statuses'), 'options' => $statusOptions, 'values' => $statuses])
        @include('partials.filter-multi', ['name' => 'area', 'label' => __('reservation.filter_area'), 'all' => __('reservation.all_areas'), 'options' => $areaOptions, 'values' => $areaIds])
        @include('partials.filter-multi', ['name' => 'source', 'label' => __('reservation.source_label'), 'all' => __('reservation.all_sources'), 'options' => $sourceOptions, 'values' => $sources])
    @endcomponent

    <p class="lead">{{ __('reservation.manage_summary', ['count' => $reservations->total()]) }}</p>

    @if ($reservations->isEmpty())
        <p class="empty">{{ __('reservation.empty') }}</p>
    @else
        @foreach ($reservations->getCollection()->groupBy(fn ($reservation) => $reservation->reserved_at->toDateString()) as $day => $rows)
            <section class="day-group">
                <h2>{{ day_label(\Carbon\CarbonImmutable::parse($day)) }}</h2>
                @include('modules.reservation.rows', ['rows' => $rows])
            </section>
        @endforeach
        @include('partials.pagination', ['paginator' => $reservations])
    @endif
@endsection
