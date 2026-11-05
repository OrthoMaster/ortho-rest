@extends('layouts.base')

@section('body')
@if (session()->has('impersonator_id'))
    <form class="impersonation" method="post" action="{{ route('impersonate.leave') }}">
        @csrf
        <span>{{ __('ui.impersonation.banner', ['name' => auth()->user()->name]) }}</span>
        <button type="submit">{{ __('ui.impersonation.leave') }}</button>
    </form>
@endif
<div class="shell">
    <aside class="sidebar">
        <div class="sidebar-head">
            <span class="wordmark">{{ __('ui.app') }}</span>
            @yield('sidebar_title')
        </div>
        <nav>@yield('nav')</nav>
        <form class="sidebar-foot" method="post" action="{{ route('logout') }}">
            @csrf
            <span>{{ auth()->user()->name }}</span>
            <button type="submit">{{ __('ui.nav.logout') }}</button>
        </form>
    </aside>
    <main class="content">
        @include('partials.flash')
        @yield('content')
    </main>
</div>
@endsection
