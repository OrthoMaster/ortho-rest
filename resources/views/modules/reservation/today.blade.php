@extends('layouts.admin')

@section('title', __('reservation.today'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.reservation') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.reservation.create') }}">{{ __('reservation.add') }}</a>
    </div>

    @include('modules.reservation.subnav')

    <div class="today-head">
        <h2>{{ day_label(today()) }}</h2>
        <p class="lead">{{ __('reservation.today_summary', ['count' => $count, 'seated' => $arrived, 'guests' => $guests]) }}</p>
    </div>

    @if ($pendingToday > 0)
        <p class="flash flash-warn"><a href="{{ route('tenant.admin.reservation.manage', ['status' => ['pending']]) }}">{{ __('reservation.pending_today', ['count' => $pendingToday]) }}</a></p>
    @endif

    {{-- Ca là các nút bật tắt: bấm để lọc thêm ca đó, bấm lại để bỏ. Lựa chọn được nhớ trong phiên. --}}
    <div class="today-tools">
        @if ($periods !== [])
            <nav class="chip-row" aria-label="{{ __('reservation.shift_filter') }}">
                <a @class(['chip', 'is-active' => $chosen === []]) href="{{ route('tenant.admin.reservation.index', ['shift_set' => 1]) }}" @if ($chosen === []) aria-current="true" @endif>{{ __('reservation.all_shifts') }}</a>
                @foreach ($periods as $period)
                    @php($on = in_array($period['name'], $chosen, true))
                    @php($next = $on ? array_values(array_diff($chosen, [$period['name']])) : [...$chosen, $period['name']])
                    <a @class(['chip', 'is-active' => $on]) href="{{ route('tenant.admin.reservation.index', ['shift_set' => 1, 'shift' => $next]) }}" @if ($on) aria-current="true" @endif>
                        {{ $period['name'] }} <span class="chip-count">{{ $shiftCounts[$period['name']] ?? 0 }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
        <label class="list-search">
            <span class="visually-hidden">{{ __('reservation.today_search') }}</span>
            <input type="search" data-search-input placeholder="{{ __('reservation.today_search') }}" autocomplete="off">
        </label>
    </div>

    <p class="empty" data-search-empty hidden>{{ __('reservation.today_search_empty') }}</p>

    @if ($count === 0 && $groups->every(fn ($group) => $group['rows']->isEmpty()) && $chosen === [])
        <p class="empty">{{ __('reservation.today_empty') }}</p>
    @else
        @foreach ($groups as $group)
            <section class="day-group" data-search-group="">
                @if ($group['name'])
                    <h2>{{ $group['name'] }} @if ($group['range'])<small>{{ $group['range'] }}</small>@endif</h2>
                @endif
                @if ($group['rows']->isEmpty())
                    <p class="row-note" data-search-hide>{{ __('reservation.shift_empty') }}</p>
                @else
                    @include('modules.reservation.rows', ['rows' => $group['rows'], 'today' => true])
                @endif
            </section>
        @endforeach
    @endif

    @include('modules.reservation.overbook-prompt')
@endsection
