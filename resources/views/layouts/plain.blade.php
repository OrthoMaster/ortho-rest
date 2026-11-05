@extends('layouts.base')

@section('body_class', 'plain')

@section('body')
<main class="plain-main">
    @unless (tenant())
        <a class="plain-brand" href="{{ url('/') }}">@include('partials.liresto-logo')</a>
    @endunless
    <div class="plain-box">
        @yield('content')
    </div>
</main>
@endsection
