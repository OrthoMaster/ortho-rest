@php($links = [])
@can('dinein.order')
    @php($links[] = ['url' => route('tenant.admin.dinein.tables.index'), 'label' => __('dinein.tables'), 'active' => request()->routeIs('tenant.admin.dinein.tables.*')])
@endcan
@can('dinein.bills')
    @php($links[] = ['url' => route('tenant.admin.dinein.bills'), 'label' => __('dinein.bills'), 'active' => request()->routeIs('tenant.admin.dinein.bills')])
@endcan
@can('dinein.vouchers')
    @php($links[] = ['url' => route('tenant.admin.dinein.offers'), 'label' => __('dinein.offers'), 'active' => request()->routeIs('tenant.admin.dinein.offers')])
@endcan
@can('dinein.incidents')
    @php($links[] = ['url' => route('tenant.admin.dinein.incidents'), 'label' => __('dinein.incidents'), 'active' => request()->routeIs('tenant.admin.dinein.incidents')])
@endcan
@if (count($links) > 1)
    @include('partials.subnav', ['links' => $links])
@endif
