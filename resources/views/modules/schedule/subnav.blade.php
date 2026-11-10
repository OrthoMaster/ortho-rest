{{-- Ba cách xem cùng một lịch; giữ ngày đang xem khi đổi tab. --}}
@can('schedule.view')
    @php($query = ['date' => $date->toDateString()])
    @include('partials.subnav', ['links' => [
        ['url' => route('tenant.admin.schedule.week', $query), 'label' => __('schedule.week'), 'active' => request()->routeIs('tenant.admin.schedule.week')],
        ['url' => route('tenant.admin.schedule.day', $query), 'label' => __('schedule.day'), 'active' => request()->routeIs('tenant.admin.schedule.day')],
        ['url' => route('tenant.admin.schedule.person', $query + ['user' => request('user')]), 'label' => __('schedule.person'), 'active' => request()->routeIs('tenant.admin.schedule.person')],
    ]])
@endcan
