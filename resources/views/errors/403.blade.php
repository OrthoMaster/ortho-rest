@extends('layouts.plain')

@section('title', __('ui.errors.403_title'))

@section('content')
    <h1>{{ __('ui.errors.403_title') }}</h1>
    <p class="lead">{{ __('ui.errors.403_lead') }}</p>
@endsection
