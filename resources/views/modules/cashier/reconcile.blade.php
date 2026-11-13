@extends('layouts.admin')

@section('title', __('cashier.reconcile_tab'))

@section('content')
    <div class="page-head">
        <h1>{{ __('cashier.title') }}</h1>
        <a class="btn btn-quiet" href="{{ route('tenant.admin.cashier.reconcile.export', request()->query()) }}">{{ __('cashier.export') }}</a>
    </div>

    @include('modules.cashier.subnav')

    <p class="lead">{{ __('cashier.reconcile_lead') }}</p>

    @php($keep = ['from' => $from->toDateString(), 'to' => $to->toDateString()])
    @php($both = tenant()->hasModule('dinein') && tenant()->hasModule('takeaway'))
    @php($pills = [])
    @foreach (\App\Modules\Cashier\Http\ReconcileController::FILTERS as $name => $values)
        @if ($name !== 'source' || $both)
            @php($pills = [...$pills, ...filter_pills($name, __('cashier.filters.'.$name), $filters[$name])])
        @endif
    @endforeach
    @if ($search !== '')
        @php($pills[] = ['label' => $search, 'url' => request()->fullUrlWithQuery(['q' => null, 'page' => null])])
    @endif
    {{-- Máy tính: ngày và bộ lọc cột trái; tổng và danh sách cột phải. --}}
    <div class="layout-side">
        <aside>
            @component('partials.filter-bar', ['id' => 'reconcile', 'class' => 'is-side', 'keep' => $keep, 'pills' => $pills, 'clearUrl' => url()->current().'?'.http_build_query($keep)])
                @slot('date')
                    @include('partials.date-range-nav', ['from' => $from, 'to' => $to, 'max' => today()->toDateString(), 'maxDays' => \App\Modules\Cashier\Http\ReconcileController::MAX_DAYS])
                @endslot
                <div class="field">
                    <label for="reconcile-search">{{ __('cashier.reconcile_search') }}</label>
                    <input id="reconcile-search" type="search" name="q" value="{{ $search }}" autocomplete="off">
                </div>
                @include('partials.filter-multi', ['name' => 'paid', 'label' => __('cashier.filter_paid'), 'all' => __('cashier.all'), 'options' => __('cashier.filters.paid'), 'values' => $filters['paid']])
                @include('partials.filter-multi', ['name' => 'number', 'label' => __('cashier.filter_number'), 'all' => __('cashier.all'), 'options' => __('cashier.filters.number'), 'values' => $filters['number']])
                @if ($both)
                    @include('partials.filter-multi', ['name' => 'source', 'label' => __('cashier.filter_source'), 'all' => __('cashier.all'), 'options' => __('cashier.filters.source'), 'values' => $filters['source']])
                @endif
            @endcomponent
        </aside>

        <div class="stack">
            <section class="panel cash-totals">
                <div><small>{{ __('cashier.sum_count') }}</small><strong>{{ $totals['count'] }}</strong></div>
                <div><small>{{ __('cashier.sum_paid') }}</small><strong>{{ vnd($totals['paid']) }}</strong></div>
                <div @class(['is-warn' => $totals['unpaid_count'] > 0])>
                    <small>{{ __('cashier.sum_unpaid') }}</small><strong>{{ vnd($totals['unpaid']) }}</strong>
                    <small>{{ __('cashier.sum_unpaid_detail', ['count' => $totals['unpaid_count']]) }}</small>
                </div>
                <div @class(['is-warn' => $totals['missing'] > 0])><small>{{ __('cashier.sum_missing') }}</small><strong>{{ $totals['missing'] }}</strong></div>
            </section>

            @if ($bills->isEmpty())
                <p class="empty">{{ __('cashier.reconcile_none') }}</p>
            @else
                <section class="panel">
                    <ul class="rows cash-list">
                        @foreach ($bills as $bill)
                            @if ($bill->paidAt())
                                @include('modules.cashier.row', ['bill' => $bill, 'waiting' => false])
                            @else
                                {{-- Chưa thu: ghi rõ, người có quyền thu tiền thì thu luôn ở đây. --}}
                                <li class="row-link">
                                    <span class="row-name">
                                        <button class="row-open" type="button" @can('cashier.collect') data-open-dialog="cash-{{ $bill->key() }}" @endcan>{{ $bill->title() }}@if ($bill->customer())<span class="row-note"> · {{ $bill->customer() }}</span>@endif</button>
                                        <small>{{ $bill->state() }}</small>
                                    </span>
                                    <span class="badge badge-bad">{{ __('cashier.unpaid') }}</span>
                                    <span class="cash-amount"><strong>{{ vnd($bill->total()) }}</strong></span>
                                    @can('cashier.collect')
                                        <button class="btn btn-sm" type="button" data-open-dialog="cash-{{ $bill->key() }}">{{ __('cashier.collect') }}</button>
                                    @endcan
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </section>
                @include('partials.pagination', ['paginator' => $bills])

                @foreach ($bills as $bill)
                    @include('modules.cashier.dialogs', ['bill' => $bill])
                @endforeach
            @endif
        </div>
    </div>
@endsection
