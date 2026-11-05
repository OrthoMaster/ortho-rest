@extends('layouts.admin')

@section('title', __('ui.profile.title'))

@section('content')
    <h1>{{ __('ui.profile.title') }}</h1>

    <section class="panel narrow">
        <h2>{{ __('ui.profile.info') }}</h2>
        <form method="post" action="{{ route('tenant.admin.profile.update') }}" class="stack" data-guard>
            @csrf
            @method('put')
            @include('partials.field', ['name' => 'name', 'label' => __('ui.profile.name'), 'value' => $user->name, 'autocomplete' => 'name'])
            @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.profile.email'), 'value' => $user->email, 'autocomplete' => 'username'])
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </form>
    </section>

    <section class="panel narrow">
        <h2>{{ __('ui.profile.change_password') }}</h2>
        <form method="post" action="{{ route('tenant.admin.profile.password') }}" class="stack" data-guard>
            @csrf
            @method('put')
            @include('partials.field', ['name' => 'current_password', 'type' => 'password', 'label' => __('ui.profile.current_password'), 'autocomplete' => 'current-password'])
            @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.password.new'), 'autocomplete' => 'new-password'])
            @include('partials.field', ['name' => 'password_confirmation', 'type' => 'password', 'label' => __('ui.password.confirm'), 'autocomplete' => 'new-password'])
            <button class="btn" type="submit">{{ __('ui.profile.password_submit') }}</button>
        </form>
    </section>
@endsection
