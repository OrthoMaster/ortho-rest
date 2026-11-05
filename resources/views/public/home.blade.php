@extends('layouts.plain')

@section('title', $restaurant->name)

@section('content')
    <h1>{{ $restaurant->name }}</h1>
    <p class="lead">{{ __('ui.public.coming_soon') }}</p>
@endsection
