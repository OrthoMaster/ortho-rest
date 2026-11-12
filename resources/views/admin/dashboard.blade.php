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

    <div class="cols cols-2">
    <section class="panel">
        <h2>{{ __('ui.dashboard.public_url') }}</h2>
        @if ($restaurant->hasModule('website'))
            <p><a href="{{ tenant_route('public.home') }}">{{ tenant_route('public.home') }}</a></p>
        @else
            <p>{{ __('ui.dashboard.site_off') }}</p>
        @endif
    </section>

    <section class="panel">
        <h2>{{ __('ui.dashboard.modules_title') }}</h2>
        @php($enabled = array_filter(config('modules'), fn ($module, $key) => ! ($module['core'] ?? false) && $restaurant->hasModule($key), ARRAY_FILTER_USE_BOTH))
        @if ($enabled)
            <ul class="tags">
                @foreach ($enabled as $module)
                    <li>{{ __($module['name']) }}</li>
                @endforeach
            </ul>
        @else
            <p>{{ __('ui.dashboard.modules_empty') }}</p>
        @endif
    </section>
    </div>
@endsection
