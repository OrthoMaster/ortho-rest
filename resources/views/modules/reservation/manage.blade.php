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

    {{-- Hàng trên: ngày bên trái, trạng thái bên phải; rồi các pill; ô tìm ở hàng dưới, sát phải. --}}
    @component('partials.filter-bar', ['id' => 'reservation-manage', 'class' => 'is-split', 'pills' => $pills, 'clearUrl' => url()->current()])
        @slot('date')
            <form method="get" onchange="this.submit()">
                @include('partials.query-hidden', ['except' => ['from', 'to', 'page']])
                @include('partials.date-range-picker', ['label' => __('reservation.date_filter'), 'from' => $from, 'to' => $to, 'max' => null,
                    'maxDays' => \App\Modules\Reservation\Http\ReservationController::MAX_RANGE_DAYS, 'longShortcut' => false, 'placeholder' => __('reservation.upcoming')])
            </form>
        @endslot
        @include('partials.query-hidden', ['except' => ['status', 'page']])
        @include('partials.filter-multi', ['name' => 'status', 'label' => __('reservation.filter_status'), 'all' => __('reservation.all_statuses'), 'options' => $statusOptions, 'values' => $statuses])
    @endcomponent

    {{-- Gõ là lọc ngay trên trang; Enter thì tìm trong toàn bộ đặt bàn. --}}
    <form method="get" class="list-search">
        @include('partials.query-hidden', ['except' => ['q', 'page']])
        <label>
            <span class="visually-hidden">{{ __('reservation.today_search') }}</span>
            <input type="search" name="q" value="{{ $search }}" data-search-input placeholder="{{ __('reservation.today_search') }}" autocomplete="off">
        </label>
    </form>

    <p class="lead">{{ $upcoming ? __('reservation.manage_summary_upcoming', ['count' => $reservations->total()]) : __('reservation.manage_summary', ['count' => $reservations->total()]) }}</p>
    <p class="empty" data-search-empty hidden>{{ __('reservation.today_search_empty') }}</p>

    @if ($reservations->isEmpty())
        <p class="empty">{{ __('reservation.manage_empty') }}</p>
    @else
        @foreach ($reservations->getCollection()->groupBy(fn ($reservation) => $reservation->reserved_at->toDateString()) as $day => $rows)
            <section class="day-group" data-search-group="">
                <h2>{{ day_label(\Carbon\CarbonImmutable::parse($day)) }}</h2>
                @include('modules.reservation.rows', ['rows' => $rows])
            </section>
        @endforeach
        @include('partials.pagination', ['paginator' => $reservations])
    @endif

    @include('modules.reservation.overbook-prompt')
@endsection
