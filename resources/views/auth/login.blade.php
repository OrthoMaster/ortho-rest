@extends('layouts.plain')

@php($prefix = tenant() ? 'tenant.' : '')

@section('title', __('ui.login.title'))

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
        <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('ui.login.remember') }}</label>
        <button class="btn" type="submit">{{ __('ui.login.submit') }}</button>
    </form>
    <p class="aside-link"><a href="{{ route($prefix.'password.request') }}">{{ __('ui.login.forgot') }}</a></p>
@endsection
