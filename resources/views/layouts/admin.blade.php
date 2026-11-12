@extends('layouts.shell')

@section('sidebar_head')
    @include('partials.restaurant-logo', ['restaurant' => tenant()])
    <strong class="sidebar-name">{{ tenant()->name }}</strong>
@endsection

@section('nav')
    {{-- Mục của một module: chỉ hiện khi module bật và người dùng có một trong các quyền của mục. --}}
    @php($moduleLink = function (string $key, ?string $except = null) {
        $module = config('modules.'.$key);

        if (! isset($module['menu']) || ! has_module($key) || ! \Illuminate\Support\Facades\Gate::any((array) $module['menu']['permission'])) {
            return '';
        }

        return '<a href="'.e(route('tenant.admin.'.$module['menu']['route'])).'"'.(request()->routeIs('tenant.admin.'.$key.'.*') && ! ($except && request()->routeIs($except)) ? ' class="active"' : '').'>'.e(__($module['name'])).'</a>';
    })
    {{-- Các mục phục vụ khách hằng ngày đứng riêng một nhóm, có đường kẻ trên dưới. --}}
    @php($service = implode('', array_map($moduleLink, ['dinein', 'reservation', 'takeaway', 'kitchen', 'menu'])))

    <a href="{{ route('tenant.admin.dashboard') }}" @class(['active' => request()->routeIs('tenant.admin.dashboard')])>{{ __('ui.nav.dashboard') }}</a>
    {!! $moduleLink('website') !!}
    @if ($service !== '')
        <hr class="nav-sep">
        {!! $service !!}
        <hr class="nav-sep">
    @endif
    @if ((has_module('reservation') || has_module('takeaway')) && auth()->user()->can('customers.view'))
        <a href="{{ route('tenant.admin.customers.index') }}" @class(['active' => request()->routeIs('tenant.admin.customers.*')])>{{ __('ui.nav.customers') }}</a>
    @endif
    {{-- Nhóm nội bộ: nhân viên, lịch làm việc, nghỉ phép (đơn xin nghỉ và ngày phép tách khỏi Lịch làm việc). --}}
    @php($leaveLink = $moduleLink('schedule') !== ''
        ? '<a href="'.e(route(auth()->user()->can('schedule.leave') ? 'tenant.admin.schedule.leave.index' : 'tenant.admin.schedule.leave.mine')).'"'.(request()->routeIs('tenant.admin.schedule.leave.*') ? ' class="active"' : '').'>'.e(__('leave.nav')).'</a>'
        : '')
    @php($team = $moduleLink('staff').$moduleLink('schedule', 'tenant.admin.schedule.leave.*').$leaveLink)
    @if ($team !== '')
        <hr class="nav-sep">
        {!! $team !!}
        <hr class="nav-sep">
    @endif
    @can('activity.view')
        <a href="{{ route('tenant.admin.activity') }}" @class(['active' => request()->routeIs('tenant.admin.activity')])>{{ __('activity.title') }}</a>
    @endcan
    @can('settings.manage')
        <a href="{{ route('tenant.admin.restaurant.edit') }}" @class(['active' => request()->routeIs('tenant.admin.restaurant.*')])>{{ __('ui.restaurant.title') }}</a>
    @endcan
    <a href="{{ route('tenant.admin.profile.edit') }}" @class(['active' => request()->routeIs('tenant.admin.profile.*')])>{{ __('ui.nav.profile') }}</a>
@endsection

{{-- Báo món trên mọi trang: món đã xong cho người phục vụ, món mới cho người ở bếp, bar. --}}
@if (has_module('dinein') && \Illuminate\Support\Facades\Gate::any(['dinein.order', 'kitchen.screen']))
    @push('overlays')
        @include('modules.dinein.alerts')
    @endpush
@endif
