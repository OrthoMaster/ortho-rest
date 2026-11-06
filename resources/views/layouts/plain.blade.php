@extends('layouts.base')

@section('body_class', 'plain')

@section('body')
<main class="plain-main">
    @if (tenant())
        <div class="plain-brand tenant-brand">
            @include('partials.restaurant-logo', ['restaurant' => tenant(), 'size' => 'lg'])
            <span>{{ tenant()->name }}</span>
        </div>
    @else
        <a class="plain-brand" href="{{ url('/') }}">@include('partials.liresto-logo')</a>
    @endif
    <div class="plain-box">
        @yield('content')
    </div>
</main>
@endsection
