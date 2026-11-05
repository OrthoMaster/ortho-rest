@extends('layouts.plain')

@section('title', __('ui.login.title'))

@section('content')
    <h1>{{ __('ui.login.title') }}</h1>
    @include('partials.flash')
    <form method="post" action="{{ route('login') }}" class="stack">
        @csrf
        @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.login.email'), 'autocomplete' => 'username'])
        @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.login.password'), 'autocomplete' => 'current-password'])
        <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('ui.login.remember') }}</label>
        <button class="btn" type="submit">{{ __('ui.login.submit') }}</button>
    </form>
    <p class="aside-link"><a href="{{ route('password.request') }}">{{ __('ui.login.forgot') }}</a></p>
@endsection
