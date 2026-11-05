@php($links = [['url' => route('tenant.admin.takeaway.index'), 'label' => __('takeaway.orders'), 'active' => request()->routeIs('tenant.admin.takeaway.index')]])
@if (in_array(auth()->user()->role?->value, ['owner', 'manager'], true))
    @if (config('liresto.discount_codes'))
        @php($links[] = ['url' => route('tenant.admin.takeaway.discounts.index'), 'label' => __('takeaway.discounts'), 'active' => request()->routeIs('tenant.admin.takeaway.discounts.*')])
    @endif
    @php($links[] = ['url' => route('tenant.admin.takeaway.settings.edit'), 'label' => __('takeaway.settings'), 'active' => request()->routeIs('tenant.admin.takeaway.settings.*')])
@endif
@include('partials.subnav', ['links' => $links])
