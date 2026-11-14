@extends('layouts.admin')

@section('title', __('ui.nav.dashboard'))

@section('content')
    <h1>{{ __('ui.dashboard.greeting', ['name' => $user->name]) }}</h1>
    @php($roleLabel = $user->isOwner() ? $user->role->label() : ($user->positions->pluck('name')->implode(', ') ?: $user->role?->label()))
    <p class="lead">{{ __('ui.dashboard.role_at', ['role' => $roleLabel, 'restaurant' => $restaurant->name]) }}</p>

    @if ($service)
        {{-- Vào nhanh các màn hình phục vụ, kèm số việc đang chờ. --}}
        <section class="stack dashboard-screens">
            <h2>{{ __('ui.dashboard.service') }}</h2>
            <ul class="table-grid station-grid">
                @foreach ($service as $tile)
                    <li>
                        <a href="{{ route('tenant.admin.'.$tile['route']) }}" @class(['table-tile', 'is-open' => $tile['count'] > 0])>
                            <strong>{{ $tile['label'] }}</strong>
                            <small @class(['badge badge-warn' => $tile['count'] > 0])>{{ $tile['note'] }}</small>
                            @if ($tile['alert'] > 0)
                                <b class="tile-count" title="{{ $tile['alert_note'] }}">{{ $tile['alert'] }}<span class="visually-hidden"> {{ $tile['alert_note'] }}</span></b>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($screens && ($prep || $stations->isNotEmpty()))
        {{-- Vào nhanh màn hình khu vực. --}}
        <section class="stack dashboard-screens">
            <h2>{{ __('kitchen.screens') }}</h2>
            @include('modules.kitchen.tiles')
        </section>
    @endif
@endsection
