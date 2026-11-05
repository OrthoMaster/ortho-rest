@extends('layouts.admin')

@section('title', __('modules.'.$module))

@section('content')
    <h1>{{ __('modules.'.$module) }}</h1>
    <p class="lead">{{ __('ui.placeholder.lead') }}</p>
@endsection
