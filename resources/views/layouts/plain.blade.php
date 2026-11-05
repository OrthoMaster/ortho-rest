@extends('layouts.base')

@section('body_class', 'plain')

@section('body')
<main class="plain-main">
    <a class="wordmark" href="{{ url('/') }}">{{ __('ui.app') }}</a>
    <div class="plain-box">
        @yield('content')
    </div>
</main>
@endsection
