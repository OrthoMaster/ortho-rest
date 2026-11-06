@extends('layouts.admin')

@section('title', __('reservation.manage'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.reservation') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.reservation.create') }}">{{ __('reservation.add') }}</a>
    </div>

    @include('modules.reservation.subnav')

    @php($statusOptions = collect(\App\Modules\Reservation\ReservationStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())
    @php($pills = [])
    @if ($from)
        @php($pills[] = ['label' => $from->equalTo($to) ? $from->format('d/m/Y') : $from->format('d/m').' – '.$to->format('d/m/Y'), 'url' => request()->fullUrlWithQuery(['from' => null, 'to' => null, 'page' => null])])
    @endif
    @if ($search !== '')
        @php($pills[] = ['label' => '“'.$search.'”', 'url' => request()->fullUrlWithQuery(['q' => null, 'page' => null])])
    @endif
    @php($pills = [...$pills, ...filter_pills('status', $statusOptions, $statuses)])

    @component('partials.filter-bar', ['id' => 'reservation-manage', 'pills' => $pills, 'clearUrl' => url()->current()])
        @slot('date')
            {{-- Gõ là lọc ngay trên trang; Enter thì tìm trong toàn bộ đặt bàn. --}}
            <label class="today-search">
                <span class="visually-hidden">{{ __('reservation.today_search') }}</span>
                <input type="search" name="q" value="{{ $search }}" form="filter-panel-reservation-manage" data-search-input placeholder="{{ __('reservation.today_search') }}" autocomplete="off">
            </label>
        @endslot
        @include('partials.date-range-picker', ['label' => __('reservation.date_filter'), 'showLabel' => true, 'from' => $from, 'to' => $to, 'max' => null,
            'maxDays' => \App\Modules\Reservation\Http\ReservationController::MAX_RANGE_DAYS, 'placeholder' => __('reservation.upcoming')])
        @include('partials.filter-multi', ['name' => 'status', 'label' => __('reservation.filter_status'), 'all' => __('reservation.all_statuses'), 'options' => $statusOptions, 'values' => $statuses])
    @endcomponent

    <p class="lead">{{ $upcoming ? __('reservation.manage_summary_upcoming', ['count' => $reservations->total()]) : __('reservation.manage_summary', ['count' => $reservations->total()]) }}</p>
    <p class="empty" data-search-empty hidden>{{ __('reservation.today_search_empty') }}</p>

    @if ($reservations->isEmpty())
        <p class="empty">{{ __('reservation.empty') }}</p>
    @else
        @foreach ($reservations->getCollection()->groupBy(fn ($reservation) => $reservation->reserved_at->toDateString()) as $day => $rows)
            <section class="day-group" data-search-group="">
                <h2>{{ day_label(\Carbon\CarbonImmutable::parse($day)) }}</h2>
                @include('modules.reservation.rows', ['rows' => $rows])
            </section>
        @endforeach
        @include('partials.pagination', ['paginator' => $reservations])
    @endif
@endsection
