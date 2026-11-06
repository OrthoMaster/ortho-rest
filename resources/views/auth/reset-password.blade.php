@extends('layouts.plain')

@php($prefix = tenant() ? 'tenant.' : '')

@section('title', __('ui.password.reset_title'))

@section('content')
    <h1>{{ __('ui.password.reset_title') }}</h1>
    <form method="post" action="{{ route($prefix.'password.update') }}" class="stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.login.email'), 'value' => $email, 'autocomplete' => 'username'])
        @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.password.new'), 'autocomplete' => 'new-password'])
        @include('partials.field', ['name' => 'password_confirmation', 'type' => 'password', 'label' => __('ui.password.confirm'), 'autocomplete' => 'new-password'])
        <button class="btn" type="submit">{{ __('ui.password.reset_submit') }}</button>
    </form>
@endsection
