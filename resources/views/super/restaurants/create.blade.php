@extends('layouts.super')

@section('title', __('ui.super.create'))

@section('content')
    @include('partials.page-title', ['title' => __('ui.super.create'), 'back' => back_url(route('super.restaurants.index'))])

    <form method="post" action="{{ route('super.restaurants.store') }}" class="panel narrow stack" data-guard>
        @csrf
        @include('partials.field', ['name' => 'name', 'label' => __('ui.super.name')])
        @include('partials.field', ['name' => 'slug', 'label' => __('ui.super.slug'), 'required' => false, 'hint' => __('ui.super.slug_hint')])
        <h2>{{ __('ui.super.owner_section') }}</h2>
        @include('partials.field', ['name' => 'owner_name', 'label' => __('ui.super.owner_name')])
        @include('partials.field', ['name' => 'owner_email', 'type' => 'email', 'label' => __('ui.super.owner_email'), 'autocomplete' => 'off'])
        @include('partials.field', ['name' => 'owner_password', 'type' => 'password', 'label' => __('ui.super.owner_password'), 'autocomplete' => 'new-password'])
        <button class="btn" type="submit">{{ __('ui.super.create_submit') }}</button>
    </form>
@endsection
