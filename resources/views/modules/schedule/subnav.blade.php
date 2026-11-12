{{-- Lịch tuần (chỉ xem), Phân công, Lịch mặc định, Cài đặt (đơn xin nghỉ, ngày phép ở mục Nghỉ phép); giữ tuần đang xem khi đổi tab. --}}
@php($query = isset($date) ? ['date' => $date->toDateString()] : [])
@php($links = [['url' => route('tenant.admin.schedule.week', $query), 'label' => __('schedule.week'), 'active' => request()->routeIs('tenant.admin.schedule.week')]])
@can('schedule.manage')
    @php($links[] = ['url' => route('tenant.admin.schedule.assign', $query), 'label' => __('schedule.assign'), 'active' => request()->routeIs('tenant.admin.schedule.assign')])
    @php($links[] = ['url' => route('tenant.admin.schedule.defaults'), 'label' => __('schedule.defaults'), 'active' => request()->routeIs('tenant.admin.schedule.defaults')])
@endcan
@can('schedule.manage')
    @php($links[] = ['url' => route('tenant.admin.schedule.settings'), 'label' => __('schedule.settings'), 'active' => request()->routeIs('tenant.admin.schedule.settings')])
@endcan
@include('partials.subnav', ['links' => $links])
