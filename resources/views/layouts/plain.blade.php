@extends('layouts.base')

@section('body_class', 'plain')

@section('body')
<main class="plain-main">
    @unless (tenant())
        <a class="wordmark" href="{{ url('/') }}">{{ __('ui.app') }}</a>
    @endunless
    <div class="plain-box">
        @yield('content')
    </div>
</main>
@endsection
