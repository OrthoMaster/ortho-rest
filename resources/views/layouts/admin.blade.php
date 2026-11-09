@extends('layouts.shell')

@section('sidebar_head')
    @include('partials.restaurant-logo', ['restaurant' => tenant()])
    <strong class="sidebar-name">{{ tenant()->name }}</strong>
@endsection

@section('nav')
    <a href="{{ route('tenant.admin.dashboard') }}" @class(['active' => request()->routeIs('tenant.admin.dashboard')])>{{ __('ui.nav.dashboard') }}</a>
    @foreach (config('modules') as $key => $module)
        @if (isset($module['menu']) && has_module($key) && \Illuminate\Support\Facades\Gate::any((array) $module['menu']['permission']))
            <a href="{{ route('tenant.admin.'.$module['menu']['route']) }}" @class(['active' => request()->routeIs('tenant.admin.'.$key.'.*')])>{{ __($module['name']) }}</a>
        @endif
    @endforeach
    @if ((has_module('reservation') || has_module('takeaway')) && auth()->user()->can('customers.view'))
        <a href="{{ route('tenant.admin.customers.index') }}" @class(['active' => request()->routeIs('tenant.admin.customers.*')])>{{ __('ui.nav.customers') }}</a>
    @endif
    @can('activity.view')
        <a href="{{ route('tenant.admin.activity') }}" @class(['active' => request()->routeIs('tenant.admin.activity')])>{{ __('activity.title') }}</a>
    @endcan
    @can('settings.manage')
        <a href="{{ route('tenant.admin.restaurant.edit') }}" @class(['active' => request()->routeIs('tenant.admin.restaurant.*')])>{{ __('ui.restaurant.title') }}</a>
    @endcan
    <a href="{{ route('tenant.admin.profile.edit') }}" @class(['active' => request()->routeIs('tenant.admin.profile.*')])>{{ __('ui.nav.profile') }}</a>
@endsection
