@php($links = [])
@can('dinein.order')
    @php($links[] = ['url' => route('tenant.admin.dinein.tables.index'), 'label' => __('dinein.tables'), 'active' => request()->routeIs('tenant.admin.dinein.tables.*')])
@endcan
@can('dinein.screen')
    @php($links[] = ['url' => route('tenant.admin.dinein.screens.index'), 'label' => __('dinein.screens'), 'active' => request()->routeIs('tenant.admin.dinein.screens.*')])
@endcan
@can('dinein.assign')
    @php($links[] = ['url' => route('tenant.admin.dinein.assign.index'), 'label' => __('dinein.assign'), 'active' => request()->routeIs('tenant.admin.dinein.assign.*')])
@endcan
@can('dinein.settings')
    @php($links[] = ['url' => route('tenant.admin.dinein.stations.index'), 'label' => __('dinein.stations'), 'active' => request()->routeIs('tenant.admin.dinein.stations.*')])
@endcan
@if (count($links) > 1)
    @include('partials.subnav', ['links' => $links])
@endif
