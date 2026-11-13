@php($links = [])
@can('cashier.collect')
    @php($links[] = ['url' => route('tenant.admin.cashier.index'), 'label' => __('cashier.collect_tab'), 'active' => request()->routeIs('tenant.admin.cashier.index')])
@endcan
@can('cashier.reconcile')
    @php($links[] = ['url' => route('tenant.admin.cashier.reconcile'), 'label' => __('cashier.reconcile_tab'), 'active' => request()->routeIs('tenant.admin.cashier.reconcile')])
@endcan
@if (count($links) > 1)
    @include('partials.subnav', ['links' => $links])
@endif
