@extends('layouts.plain')

@section('title', $restaurant->name)

@section('content')
    @include('partials.restaurant-logo', ['restaurant' => $restaurant, 'size' => 'lg'])
    <h1>{{ $restaurant->name }}</h1>
    <p class="lead">{{ __('ui.public.coming_soon') }}</p>
    @if (has_module('reservation'))
        <div class="public-actions">
            <a class="btn" href="{{ tenant_route('public.booking') }}">{{ __('website.booking.title') }}</a>
        </div>
    @endif
@endsection
