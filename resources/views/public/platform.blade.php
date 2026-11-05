@extends('layouts.base')

@section('title', __('ui.app'))
@section('body_class', 'platform')

@section('body')
<main class="platform-main">
    <img class="platform-mark" src="{{ asset('images/liresto-mark.svg') }}" alt="">
    <h1>{{ __('ui.app') }}</h1>
    <p>{{ __('ui.platform.lead') }}</p>
    <a class="btn" href="{{ route('login') }}">{{ __('ui.platform.login') }}</a>
</main>
@endsection
