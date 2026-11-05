@extends('layouts.admin')

@section('title', __('ui.restaurant.title'))

@section('content')
    <h1>{{ __('ui.restaurant.title') }}</h1>

    <section class="panel">
        <h2>{{ __('ui.logo.title') }}</h2>
        @include('partials.logo-form', [
            'restaurant' => $restaurant,
            'updateUrl' => route('tenant.admin.restaurant.logo.update'),
            'destroyUrl' => route('tenant.admin.restaurant.logo.destroy'),
        ])
    </section>
@endsection
