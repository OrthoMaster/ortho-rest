@extends('layouts.shell')

@section('sidebar_head')
    {{-- Bấm logo, tên quán là về Tổng quan. --}}
    <a class="sidebar-brand" href="{{ route('tenant.admin.dashboard') }}">
        @include('partials.restaurant-logo', ['restaurant' => tenant()])
        <strong class="sidebar-name">{{ tenant()->name }}</strong>
    </a>
@endsection

@section('nav_alert', \App\Support\Attention::total(auth()->user()) > 0 ? '1' : '')

@section('nav')
    {{-- Mục của một module: chỉ hiện khi module bật và người dùng có một trong các quyền của mục. --}}
    @php($moduleLink = function (string $key, ?string $except = null) {
        $module = config('modules.'.$key);

        if (! isset($module['menu']) || ! has_module($key) || ! \Illuminate\Support\Facades\Gate::any((array) $module['menu']['permission'])) {
            return '';
        }

        return '<a href="'.e(route('tenant.admin.'.$module['menu']['route'])).'"'.(request()->routeIs('tenant.admin.'.$key.'.*') && ! ($except && request()->routeIs($except)) ? ' class="active"' : '').'>'.e(__($module['name'])).'</a>';
    })
    {{--
        Lấy đơn, Đặt bàn trước, Đặt mang đi, Thu ngân và Màn hình khu vực vào từ Tổng quan, không có mục riêng;
        đang ở các màn hình đó thì Tổng quan sáng. Menu ngay dưới Website, có đường kẻ bên dưới.
    --}}
    @php($service = $moduleLink('menu'))
    @php($serviceScreen = request()->routeIs('tenant.admin.dinein.*', 'tenant.admin.reservation.*', 'tenant.admin.takeaway.*', 'tenant.admin.cashier.*', 'tenant.admin.kitchen.*')
        && ! request()->routeIs('tenant.admin.dinein.stations.*'))

    {{-- Chấm đỏ có số: việc đang chờ người này xử lý (đặt bàn chờ xác nhận, phiếu chờ thu, đơn nghỉ chờ duyệt). --}}
    @php($attention = \App\Support\Attention::for(auth()->user()))
    @php($navBadge = fn (int $count) => $count > 0 ? '<span class="nav-badge" title="'.e(__('ui.nav.attention', ['count' => $count])).'">'.$count.'<span class="visually-hidden"> '.e(__('ui.nav.attention', ['count' => $count])).'</span></span>' : '')
    <a href="{{ route('tenant.admin.dashboard') }}" @class(['active' => request()->routeIs('tenant.admin.dashboard') || $serviceScreen])>{{ __('ui.nav.dashboard') }}{!! $navBadge(\App\Support\Attention::dashboard(auth()->user())) !!}</a>
    {!! $moduleLink('website') !!}
    @if ($service !== '')
        {!! $service !!}
        <hr class="nav-sep">
    @endif
    @if ((has_module('reservation') || has_module('takeaway')) && auth()->user()->can('customers.view'))
        <a href="{{ route('tenant.admin.customers.index') }}" @class(['active' => request()->routeIs('tenant.admin.customers.*')])>{{ __('ui.nav.customers') }}</a>
    @endif
    {{-- Nhóm nội bộ: nhân viên, lịch làm việc, nghỉ phép (đơn xin nghỉ và ngày phép tách khỏi Lịch làm việc). --}}
    @php($leaveLink = $moduleLink('schedule') !== ''
        ? '<a href="'.e(route(auth()->user()->can('schedule.leave') ? 'tenant.admin.schedule.leave.index' : 'tenant.admin.schedule.leave.mine')).'"'.(request()->routeIs('tenant.admin.schedule.leave.*') ? ' class="active"' : '').'>'.e(__('leave.nav')).$navBadge($attention['leave']).'</a>'
        : '')
    @php($team = $moduleLink('staff').$moduleLink('schedule', 'tenant.admin.schedule.leave.*').$leaveLink)
    @if ($team !== '')
        <hr class="nav-sep">
        {!! $team !!}
        <hr class="nav-sep">
    @endif
    @if (Gate::allows('reports.view') && \App\Http\Controllers\Admin\ReportController::tabs())
        <a href="{{ route('tenant.admin.reports') }}" @class(['active' => request()->routeIs('tenant.admin.reports*')])>{{ __('reports.title') }}</a>
    @endif
    @can('activity.view')
        <a href="{{ route('tenant.admin.activity') }}" @class(['active' => request()->routeIs('tenant.admin.activity')])>{{ __('activity.title') }}</a>
    @endcan
    @can('settings.manage')
        <a href="{{ route('tenant.admin.restaurant.edit') }}" @class(['active' => request()->routeIs('tenant.admin.restaurant.*')])>{{ __('ui.restaurant.title') }}</a>
        {{-- Email gửi khách (đặt bàn): máy chủ gửi, mẫu email. --}}
        @if (has_module('reservation'))
            <a href="{{ route('tenant.admin.mail.server') }}" @class(['active' => request()->routeIs('tenant.admin.mail.*')])>{{ __('mail.title') }}</a>
        @endif
    @endcan
    <a href="{{ route('tenant.admin.profile.edit') }}" @class(['active' => request()->routeIs('tenant.admin.profile.*')])>{{ __('ui.nav.profile') }}</a>
@endsection

{{-- Ứng dụng cài được của quán, và lời mời bật thông báo đẩy trên máy này (khi có Màn hình khu vực, hoặc người nhận đặt bàn online). --}}
@push('head')
    @include('partials.app-head')
@endpush
@if ((has_module('kitchen') || (has_module('reservation') && Gate::allows('reservation.manage'))) && \App\Push\WebPush::enabled())
    @push('content_top')
        <section class="panel push-banner w-lg" data-push data-key="{{ config('liresto.push.public_key') }}" data-url="{{ route('tenant.admin.push.subscribe') }}" data-token="{{ substr(hash('sha256', session()->getId().auth()->id()), 0, 16) }}" hidden>
            <div class="push-banner-text" data-push-enable hidden>
                <strong>{{ __('push.enable_title') }}</strong>
                <small>{{ __('push.enable_body') }}</small>
            </div>
            <div class="push-banner-text" data-push-install hidden>
                <strong>{{ __('push.install_title') }}</strong>
                <small>{{ __('push.install_body') }}</small>
            </div>
            <div class="push-banner-actions">
                <button class="btn btn-sm" type="button" data-push-enable hidden>{{ __('push.enable') }}</button>
                <button class="btn btn-sm" type="button" data-push-install data-open-dialog="app-install-ios" hidden>{{ __('push.install_how') }}</button>
                <button class="btn btn-quiet btn-sm" type="button" data-push-dismiss>{{ __('push.dismiss') }}</button>
            </div>
        </section>
    @endpush
    @push('overlays')
        @include('partials.app-install-dialog')
    @endpush
@endif

{{-- Tiếng chuông báo món quán đã chọn (Cài đặt chung). --}}
@if (has_module('kitchen'))
    @push('overlays')
        <span hidden data-alert-sound="{{ \App\Modules\Kitchen\Kitchen::sound() }}"></span>
    @endpush
@endif

{{-- Báo món trên mọi trang: món đã xong cho người phục vụ, món mới cho người ở bếp, bar. --}}
@if (has_module('kitchen') && \Illuminate\Support\Facades\Gate::any(['dinein.order', 'kitchen.screen']))
    @push('overlays')
        @include('modules.dinein.alerts')
    @endpush
@endif
