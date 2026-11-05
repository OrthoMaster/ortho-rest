@extends('layouts.shell')

@section('sidebar_title')
    <strong class="sidebar-place">{{ tenant()->name }}</strong>
@endsection

@section('nav')
    <a href="{{ route('tenant.admin.dashboard') }}" @class(['active' => request()->routeIs('tenant.admin.dashboard')])>{{ __('ui.nav.dashboard') }}</a>
    @foreach (config('modules') as $key => $module)
        @if (isset($module['menu']) && has_module($key) && in_array(auth()->user()->role?->value, $module['menu']['roles'], true))
            <a href="{{ route('tenant.admin.'.$module['menu']['route']) }}" @class(['active' => request()->routeIs('tenant.admin.'.$key.'.*')])>{{ __($module['name']) }}</a>
        @endif
    @endforeach
    <a href="{{ route('tenant.admin.profile.edit') }}" @class(['active' => request()->routeIs('tenant.admin.profile.*')])>{{ __('ui.nav.profile') }}</a>
@endsection
