@extends('layouts.plain')

@section('title', $restaurant->name)

@section('content')
    @include('partials.restaurant-logo', ['restaurant' => $restaurant, 'size' => 'lg'])
    <h1>{{ $restaurant->name }}</h1>
    <p class="lead">{{ __('ui.public.coming_soon') }}</p>
@endsection
