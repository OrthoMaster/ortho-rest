@extends('layouts.plain')

@section('title', __('ui.errors.404_title'))

@section('content')
    <h1>{{ __('ui.errors.404_title') }}</h1>
    <p class="lead">{{ __('ui.errors.404_lead') }}</p>
@endsection
