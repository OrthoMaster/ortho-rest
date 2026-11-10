@php($links = [])
@can('dinein.order')
    @php($links[] = ['url' => route('tenant.admin.dinein.tables.index'), 'label' => __('dinein.tables'), 'active' => request()->routeIs('tenant.admin.dinein.tables.*')])
@endcan
@can('dinein.screen')
    @php($links[] = ['url' => route('tenant.admin.dinein.screens.index'), 'label' => __('dinein.screens'), 'active' => request()->routeIs('tenant.admin.dinein.screens.*')])
@endcan
@if (count($links) > 1)
    @include('partials.subnav', ['links' => $links])
@endif
