@extends('layouts.shell')

@section('sidebar_head')
    @include('partials.liresto-logo')
@endsection

@section('nav')
    <a href="{{ route('super.restaurants.index') }}" @class(['active' => request()->routeIs('super.restaurants.*')])>{{ __('ui.nav.restaurants') }}</a>
@endsection
