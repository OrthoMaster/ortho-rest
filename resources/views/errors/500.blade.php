@extends('layouts.plain')

@section('title', __('ui.errors.500_title'))

@section('content')
    <h1>{{ __('ui.errors.500_title') }}</h1>
    <p class="lead">{{ __('ui.errors.500_lead') }}</p>
@endsection
