@extends('layouts.admin')

@section('title', __('kitchen.screens'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.kitchen') }}</h1>
    </div>

    @if ($stations->isEmpty() && ! $prep)
        <p class="empty">{{ __('kitchen.screens_none') }}</p>
    @else
        <p class="lead">{{ __('kitchen.screens_lead') }}</p>
        @include('modules.kitchen.tiles')
    @endif
@endsection
