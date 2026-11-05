@extends('layouts.shell')

@section('body_class', 'is-super')

@section('nav')
    <a href="{{ route('super.restaurants.index') }}" @class(['active' => request()->routeIs('super.restaurants.*')])>{{ __('ui.nav.restaurants') }}</a>
@endsection
