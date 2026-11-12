@extends('layouts.admin')

@section('title', __('ui.nav.dashboard'))

@section('content')
    <h1>{{ __('ui.dashboard.greeting', ['name' => $user->name]) }}</h1>
    @php($roleLabel = $user->isOwner() ? $user->role->label() : ($user->positions->pluck('name')->implode(', ') ?: $user->role?->label()))
    <p class="lead">{{ __('ui.dashboard.role_at', ['role' => $roleLabel, 'restaurant' => $restaurant->name]) }}</p>

    @if ($screens && ($prep || $stations->isNotEmpty()))
        {{-- Vào nhanh màn hình khu vực. --}}
        <section class="stack dashboard-screens">
            <h2>{{ __('kitchen.screens') }}</h2>
            @include('modules.kitchen.tiles')
        </section>
    @endif
@endsection
