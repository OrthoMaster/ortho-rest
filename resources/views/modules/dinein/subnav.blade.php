@php($links = [])
@can('dinein.order')
    @php($links[] = ['url' => route('tenant.admin.dinein.tables.index'), 'label' => __('dinein.tables'), 'active' => request()->routeIs('tenant.admin.dinein.tables.*')])
@endcan
@can('dinein.incidents')
    @php($links[] = ['url' => route('tenant.admin.dinein.incidents'), 'label' => __('dinein.incidents'), 'active' => request()->routeIs('tenant.admin.dinein.incidents')])
@endcan
@if (count($links) > 1)
    @include('partials.subnav', ['links' => $links])
@endif
