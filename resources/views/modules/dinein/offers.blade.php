@extends('layouts.admin')

@section('title', __('dinein.offers'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.dinein') }}</h1>
    </div>

    @include('modules.dinein.subnav')

    <p class="lead">{{ __('dinein.offers_lead') }}</p>

    @php($statusOptions = collect(\App\Modules\DineIn\Http\OfferController::STATUSES)->mapWithKeys(fn ($status) => [$status => __('dinein.offer_statuses.'.$status)])->all())
    @php($keep = array_filter(['q' => $search]))
    {{-- Máy tính: ô tìm và bộ lọc cột trái, kết quả cột phải. Chỉ tìm mới có kết quả. --}}
    <div class="layout-side">
        <aside>
            @component('partials.filter-bar', ['id' => 'offers', 'class' => 'is-side', 'keep' => $keep, 'pills' => filter_pills('status', $statusOptions, $statuses), 'clearUrl' => url()->current().'?'.http_build_query($keep)])
                @slot('date')
                    <form class="list-search offer-search" method="get" role="search">
                        @foreach ($statuses as $status)
                            <input type="hidden" name="status[]" value="{{ $status }}">
                        @endforeach
                        <label>
                            <span class="visually-hidden">{{ __('dinein.offers_search') }}</span>
                            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('dinein.offers_search') }}" autocomplete="off" autocapitalize="characters" @if ($search === '') autofocus @endif>
                        </label>
                    </form>
                @endslot
                @include('partials.filter-multi', ['name' => 'status', 'label' => __('dinein.offers_filter_status'), 'all' => __('dinein.offers_all_status'), 'options' => $statusOptions, 'values' => $statuses])
            @endcomponent
        </aside>

        <div class="stack">
            @if ($offers === null)
                <p class="empty">{{ __('dinein.offers_search_hint') }}</p>
            @elseif ($offers->isEmpty())
                <p class="empty">{{ __('dinein.offers_none') }}</p>
            @else
                <section class="panel">
                    <ul class="rows offer-list">
                        @foreach ($offers as $offer)
                            <li class="row-link">
                                <span class="row-name">
                                    <button class="row-open" type="button" data-open-dialog="voucher-{{ $offer->id }}">
                                        {{ $offer->valueLabel() }} · <strong class="voucher-inline-code">{{ $offer->code }}</strong>
                                    </button>
                                    <small>{{ collect([$offer->kindLabel(), $offer->customer ? $offer->customer->name.($offer->customer->phone ? ' · '.mask_phone($offer->customer->phone) : '') : __('dinein.voucher_anonymous'), $offer->reason, $offer->sourceTable ? __('dinein.voucher_from_table', ['table' => $offer->sourceTable->label]) : null])->filter()->implode(' · ') }}</small>
                                    <small>{{ __('dinein.voucher_meta', ['time' => $offer->created_at->format('H:i d/m'), 'name' => $offer->creator?->name ?? '—']) }}{{ $offer->expires_on ? ' · '.__('dinein.voucher_until', ['date' => $offer->expires_on->format('d/m/Y')]) : '' }}</small>
                                </span>
                                <span class="badge {{ $offer->statusBadge() }}">{{ $offer->statusLabel() }}</span>
                                @component('partials.kebab', ['label' => __('dinein.voucher_actions', ['code' => $offer->code])])
                                    <button type="button" data-open-dialog="voucher-{{ $offer->id }}">{{ __('dinein.voucher_show') }}</button>
                                    @if ($offer->used_at === null && auth()->user()->can('dinein.offers'))
                                        <form method="post" action="{{ route('tenant.admin.dinein.offers.destroy', $offer) }}" data-confirm-danger
                                              data-confirm-title="{{ __('dinein.voucher_delete') }}?" data-confirm-ok="{{ __('dinein.voucher_delete') }}" data-confirm="{{ __('dinein.voucher_delete_confirm', ['code' => $offer->code]) }}">
                                            @csrf
                                            @method('delete')
                                            <button class="is-danger" type="submit">{{ __('dinein.voucher_delete') }}</button>
                                        </form>
                                    @endif
                                @endcomponent
                            </li>
                        @endforeach
                    </ul>
                </section>

                @foreach ($offers as $offer)
                    @include('modules.dinein.voucher-dialog', ['offer' => $offer])
                @endforeach
                @include('partials.voucher-scripts')
            @endif
        </div>
    </div>
@endsection
