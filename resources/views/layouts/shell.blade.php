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
        <div class="sidebar-top">
            <div class="sidebar-head">@yield('sidebar_head')</div>
            <button class="nav-toggle" type="button" aria-controls="sidebar-panel" aria-expanded="false"
                    aria-label="{{ __('ui.nav.open_menu') }}"
                    data-open-label="{{ __('ui.nav.open_menu') }}" data-close-label="{{ __('ui.nav.close_menu') }}">
                <span></span><span></span><span></span>
            </button>
        </div>
        <div class="sidebar-panel" id="sidebar-panel">
            <nav>@yield('nav')</nav>
            <form class="sidebar-foot" method="post" action="{{ route('logout') }}">
                @csrf
                <span>{{ auth()->user()->name }}</span>
                <button type="submit">{{ __('ui.nav.logout') }}</button>
            </form>
        </div>
    </aside>
    <div class="nav-backdrop"></div>
    <main class="content">
        @include('partials.flash')
        @yield('content')
    </main>
</div>
@endsection
