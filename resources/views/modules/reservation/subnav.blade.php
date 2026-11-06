@php($links = [
    ['url' => route('tenant.admin.reservation.index'), 'label' => __('reservation.list'), 'active' => request()->routeIs('tenant.admin.reservation.index')],
    ['url' => route('tenant.admin.reservation.customers'), 'label' => __('reservation.customers'), 'active' => request()->routeIs('tenant.admin.reservation.customers')],
])
@if (in_array(auth()->user()->role?->value, ['owner', 'manager'], true))
    @php($links[] = ['url' => route('tenant.admin.reservation.areas.index'), 'label' => __('reservation.areas'), 'active' => request()->routeIs('tenant.admin.reservation.areas.*')])
    @php($links[] = ['url' => route('tenant.admin.reservation.settings.edit'), 'label' => __('reservation.settings'), 'active' => request()->routeIs('tenant.admin.reservation.settings.*')])
@endif
@include('partials.subnav', ['links' => $links])
