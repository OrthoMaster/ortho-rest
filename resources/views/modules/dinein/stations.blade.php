@extends('layouts.admin')

@section('title', __('dinein.stations'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.dinein') }}</h1>
    </div>

    @include('modules.dinein.subnav')

    <p class="lead">{{ __('dinein.stations_lead') }}</p>

    @if ($unassigned > 0 && $stations->isNotEmpty())
        <p class="flash flash-warn">{{ __('dinein.unassigned', ['count' => $unassigned, 'name' => $stations->first()->name]) }}</p>
    @endif

    <section class="panel">
        <ul class="rows" data-sortable data-sort-url="{{ route('tenant.admin.dinein.stations.reorder') }}">
            @foreach ($stations as $station)
                <li class="row-link" data-sort-id="{{ $station->id }}">
                    @include('partials.drag-handle', ['label' => $station->name])
                    <span class="row-name"><a href="{{ route('tenant.admin.dinein.stations.edit', $station) }}">{{ $station->name }}</a></span>
                    <small class="row-note">{{ __('dinein.station_item_count', ['count' => $station->items_count]) }}</small>
                </li>
            @endforeach
        </ul>
        <small class="row-note">{{ __('dinein.station_fallback') }}</small>
    </section>

    <form method="post" action="{{ route('tenant.admin.dinein.stations.store') }}" class="panel inline-form">
        @csrf
        <label class="visually-hidden" for="station-name">{{ __('dinein.station_name') }}</label>
        <input id="station-name" name="name" value="{{ old('name') }}" maxlength="60" placeholder="{{ __('dinein.station_example') }}" required @error('name') aria-invalid="true" @enderror>
        <button class="btn" type="submit">{{ __('dinein.station_add') }}</button>
        @error('name')<small class="error">{{ $message }}</small>@enderror
    </form>
@endsection
