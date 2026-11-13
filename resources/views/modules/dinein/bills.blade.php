@extends('layouts.admin')

@section('title', __('dinein.bills'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.dinein') }}</h1>
        <a class="btn btn-quiet" href="{{ route('tenant.admin.dinein.bills.export', request()->query()) }}">{{ __('dinein.bills_export') }}</a>
    </div>

    @include('modules.dinein.subnav')

    <p class="lead">{{ __('dinein.bills_lead') }}</p>

    @php($keep = array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'q' => $search]))
    @php($numberOptions = ['has' => __('dinein.bills_has'), 'missing' => __('dinein.bills_missing')])
    {{-- Máy tính: ngày, ô tìm, lọc cột trái; tổng và danh sách cột phải. --}}
    <div class="layout-side">
        <aside>
            @component('partials.filter-bar', ['id' => 'bills', 'class' => 'is-side', 'keep' => $keep, 'pills' => filter_pills('number', $numberOptions, $numbers), 'clearUrl' => url()->current().'?'.http_build_query(array_diff_key($keep, ['q' => 1]))])
                @slot('date')
                    @include('partials.date-range-nav', ['from' => $from, 'to' => $to, 'max' => today()->toDateString(), 'maxDays' => \App\Modules\DineIn\Http\BillController::MAX_DAYS])
                @endslot
                <div class="field">
                    <label for="bills-search">{{ __('dinein.bills_search') }}</label>
                    <input id="bills-search" type="search" name="q" value="{{ $search }}" autocomplete="off">
                </div>
                @include('partials.filter-multi', ['name' => 'number', 'label' => __('dinein.bills_filter'), 'all' => __('dinein.bills_all'), 'options' => $numberOptions, 'values' => $numbers])
            @endcomponent
        </aside>

        <div class="stack">
            <section class="panel bill-totals">
                <div><small>{{ __('dinein.bills_count') }}</small><strong>{{ $totals->bills }}</strong></div>
                <div><small>{{ __('dinein.bills_revenue') }}</small><strong>{{ vnd((int) $totals->revenue) }}</strong></div>
                <div @class(['is-warn' => $totals->missing > 0])><small>{{ __('dinein.bills_missing_count') }}</small><strong>{{ (int) $totals->missing }}</strong></div>
            </section>

            @if ($bills->isEmpty())
                <p class="empty">{{ __('dinein.bills_none') }}</p>
            @else
                <section class="panel">
                    <ul class="rows bill-list">
                        @foreach ($bills as $table)
                            @php($total = (int) $table->bill_subtotal - (int) $table->discount_amount)
                            <li class="row-link">
                                <span class="row-name">
                                    <button class="row-open" type="button" data-open-dialog="bill-number-{{ $table->id }}">
                                        {{ __('dinein.table_title', ['table' => $table->label]) }}
                                        @if ($table->bill_number)
                                            · <strong>{{ __('dinein.bills_number', ['number' => $table->bill_number]) }}</strong>
                                        @endif
                                    </button>
                                    <small>{{ __('dinein.bills_meta', ['opened' => $table->created_at->format('H:i'), 'closed' => $table->closed_at->format('H:i d/m'), 'name' => $table->closer?->name ?? '—']) }}{{ $table->customer ? ' · '.$table->customer->name : '' }}</small>
                                </span>
                                @unless ($table->bill_number)
                                    <span class="badge badge-warn">{{ __('dinein.bills_no_number') }}</span>
                                @endunless
                                <span class="bill-amount">
                                    <strong>{{ vnd($total) }}</strong>
                                    @if ($table->discount_amount > 0)<small>{{ __('dinein.bills_discount', ['amount' => vnd($table->discount_amount)]) }}</small>@endif
                                </span>
                                @component('partials.kebab', ['label' => __('dinein.bills_actions', ['table' => $table->label])])
                                    <button type="button" data-open-dialog="bill-number-{{ $table->id }}">{{ $table->bill_number ? __('dinein.bills_edit') : __('dinein.bills_add') }}</button>
                                @endcomponent
                            </li>
                        @endforeach
                    </ul>
                </section>
                @include('partials.pagination', ['paginator' => $bills])

                @foreach ($bills as $table)
                    @php($bag = $errors->getBag('bill-'.$table->id))
                    <dialog id="bill-number-{{ $table->id }}" class="confirm-dialog" aria-labelledby="bill-number-title-{{ $table->id }}" @if ($bag->any()) data-auto-open @endif>
                        <form method="post" action="{{ route('tenant.admin.dinein.bills.update', $table) }}" class="stack" data-guard>
                            @csrf
                            @method('put')
                            <h2 id="bill-number-title-{{ $table->id }}">{{ $table->bill_number ? __('dinein.bills_edit') : __('dinein.bills_add') }}</h2>
                            <p>{{ __('dinein.table_title', ['table' => $table->label]) }} · {{ $table->closed_at->format('H:i d/m') }} · {{ vnd((int) $table->bill_subtotal - (int) $table->discount_amount) }}</p>
                            <div class="field">
                                <label for="bill-number-input-{{ $table->id }}">{{ __('dinein.bill_number') }}</label>
                                <input id="bill-number-input-{{ $table->id }}" name="bill_number" value="{{ $table->bill_number }}" maxlength="40" autocomplete="off" placeholder="{{ __('dinein.bill_number_example') }}">
                                @if ($bag->has('bill_number'))<small class="error">{{ $bag->first('bill_number') }}</small>@endif
                            </div>
                            <div class="dialog-foot">
                                <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                                <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
                            </div>
                        </form>
                    </dialog>
                @endforeach
            @endif
        </div>
    </div>
@endsection
