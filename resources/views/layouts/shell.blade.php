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
            {{-- Máy tính: thu gọn menu trái để có thêm chỗ, bấm nút ở góc trái để hiện lại. --}}
            <button class="row-btn sidebar-collapse" type="button" data-sidebar-collapse aria-label="{{ __('ui.nav.hide_menu') }}" title="{{ __('ui.nav.hide_menu') }}">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/><path d="m16 10-2 2 2 2"/></svg>
            </button>
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
    <button class="row-btn sidebar-expand" type="button" data-sidebar-expand aria-label="{{ __('ui.nav.show_menu') }}" title="{{ __('ui.nav.show_menu') }}">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/><path d="m14 10 2 2-2 2"/></svg>
    </button>
    <main class="content @yield('content_class')">
        @stack('content_top')
        @include('partials.flash')
        @yield('content')
    </main>
</div>
{{-- Xác nhận dùng chung: form có data-confirm thì hỏi lại trước khi gửi. --}}
<dialog id="confirm-dialog" aria-labelledby="confirm-dialog-title">
    <div class="stack">
        <h2 id="confirm-dialog-title" data-confirm-title data-default="{{ __('ui.common.confirm_title') }}"></h2>
        <p data-confirm-body></p>
        <div class="dialog-foot">
            <button class="btn" type="button" data-confirm-ok data-default-label="{{ __('ui.common.confirm_ok') }}"></button>
            <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
        </div>
    </div>
</dialog>
@stack('overlays')
<dialog id="unsaved-dialog">
    <div class="stack">
        <h2>{{ __('ui.unsaved.title') }}</h2>
        <p>{{ __('ui.unsaved.body') }}</p>
        <div class="dialog-foot">
            <button class="btn" type="button" data-close-dialog>{{ __('ui.unsaved.stay') }}</button>
            <button class="btn btn-danger" type="button" data-leave-page>{{ __('ui.unsaved.leave') }}</button>
        </div>
    </div>
</dialog>
@endsection
