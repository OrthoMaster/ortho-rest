@php($links = [])
@can('staff.manage')
    @php($links[] = ['url' => route('tenant.admin.staff.accounts.index'), 'label' => __('staff.accounts'), 'active' => request()->routeIs('tenant.admin.staff.accounts.*')])
@endcan
@can('positions.manage')
    @php($links[] = ['url' => route('tenant.admin.staff.positions.index'), 'label' => __('staff.positions'), 'active' => request()->routeIs('tenant.admin.staff.positions.*')])
@endcan
@include('partials.subnav', ['links' => $links])
