@extends('layouts.plain')

@section('title', __('ui.password.forgot_title'))

@section('content')
    <h1>{{ __('ui.password.forgot_title') }}</h1>
    <p class="lead">{{ __('ui.password.forgot_lead') }}</p>
    @include('partials.flash')
    <form method="post" action="{{ route('password.email') }}" class="stack">
        @csrf
        @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.login.email'), 'autocomplete' => 'username'])
        <button class="btn" type="submit">{{ __('ui.password.send_link') }}</button>
    </form>
    <p class="aside-link"><a href="{{ route('login') }}">{{ __('ui.password.back_to_login') }}</a></p>
@endsection
