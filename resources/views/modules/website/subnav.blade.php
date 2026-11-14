@php($links = [])
@if (has_module('reservation'))
    @php($links[] = ['url' => route('tenant.admin.website.booking'), 'label' => __('website.tabs.booking'), 'active' => request()->routeIs('tenant.admin.website.booking*')])
@endif
@include('partials.subnav', ['links' => $links])
