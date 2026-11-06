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
        <p class="lead">{{ __('reservation.today_summary', ['count' => $reservations->count(), 'guests' => $guests, 'seated' => $seated]) }}</p>
    </div>

    @if ($pendingToday > 0)
        <p class="flash flash-warn"><a href="{{ route('tenant.admin.reservation.manage', ['status' => ['pending']]) }}">{{ __('reservation.pending_today', ['count' => $pendingToday]) }}</a></p>
    @endif

    @if ($reservations->isEmpty())
        <p class="empty">{{ __('reservation.today_empty') }}</p>
    @else
        @include('modules.reservation.rows', ['rows' => $reservations])
    @endif
@endsection
