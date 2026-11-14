@extends('layouts.plain')

@php($prefix = tenant() ? 'tenant.' : '')

@section('title', __('ui.login.title'))

@if (tenant())
    @push('head')
        @include('partials.app-head')
    @endpush
@endif

@section('content')
    <h1>{{ __('ui.login.title') }}</h1>
    @unless (tenant())
        <p class="lead">{{ __('ui.login.super_lead') }}</p>
    @endunless
    @include('partials.flash')
    <form method="post" action="{{ route($prefix.'login') }}" class="stack">
        @csrf
        @if (tenant())
            @include('partials.field', ['name' => 'login', 'label' => __('ui.login.email_or_phone'), 'autocomplete' => 'username'])
        @else
            @include('partials.field', ['name' => 'login', 'type' => 'email', 'label' => __('ui.login.email'), 'autocomplete' => 'username'])
        @endif
        @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.login.password'), 'autocomplete' => 'current-password'])
        <label class="check"><input type="checkbox" name="remember" value="1" checked> {{ __('ui.login.remember') }}</label>
        <button class="btn" type="submit">{{ __('ui.login.submit') }}</button>
    </form>
    <p class="aside-link"><a href="{{ route($prefix.'password.request') }}">{{ __('ui.login.forgot') }}</a></p>
    @if (tenant())
        {{-- Hiện khi máy cài được: Android, máy tính dùng Chrome/Edge bấm là cài; iPhone mở hướng dẫn. --}}
        <div class="app-install" data-app-install hidden>
            <button class="btn btn-quiet" type="button">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                {{ __('push.install') }}
            </button>
            <small>{{ __('push.install_hint') }}</small>
        </div>
        @include('partials.app-install-dialog')
    @endif
@endsection
