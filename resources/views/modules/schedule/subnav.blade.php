{{-- Lịch tuần, lịch của một người, đơn xin nghỉ, cài đặt; giữ tuần đang xem khi đổi tab. --}}
@php($query = isset($date) ? ['date' => $date->toDateString()] : [])
@php($links = [])
@can('schedule.view')
    @php($links[] = ['url' => route('tenant.admin.schedule.week', $query), 'label' => __('schedule.week'), 'active' => request()->routeIs('tenant.admin.schedule.week')])
@endcan
@php($links[] = ['url' => route('tenant.admin.schedule.person', $query + ['user' => request('user')]), 'label' => \Illuminate\Support\Facades\Gate::allows('schedule.view') ? __('schedule.person') : __('schedule.my_schedule'), 'active' => request()->routeIs('tenant.admin.schedule.person')])
@php($links[] = ['url' => route('tenant.admin.schedule.leave.index'), 'label' => __('leave.title'), 'active' => request()->routeIs('tenant.admin.schedule.leave.*')])
@can('schedule.manage')
    @php($links[] = ['url' => route('tenant.admin.schedule.settings'), 'label' => __('schedule.settings'), 'active' => request()->routeIs('tenant.admin.schedule.settings')])
@endcan
@include('partials.subnav', ['links' => $links])
