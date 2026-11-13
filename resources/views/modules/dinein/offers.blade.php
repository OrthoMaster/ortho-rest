@extends('layouts.admin')

@section('title', __('dinein.offers'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.dinein') }}</h1>
    </div>

    @include('modules.dinein.subnav')

    <p class="lead">{{ __('dinein.offers_lead') }}</p>

    @if ($offers->isEmpty())
        <p class="empty">{{ __('dinein.offers_empty') }}</p>
    @else
        <section class="panel w-lg">
            <ul class="rows offer-list">
                @foreach ($offers as $offer)
                    <li class="row-link">
                        <span class="row-name">
                            <button class="row-open" type="button" data-open-dialog="voucher-{{ $offer->id }}">
                                {{ $offer->valueLabel() }} · <strong class="voucher-inline-code">{{ $offer->code }}</strong>
                            </button>
                            <small>{{ collect([$offer->kindLabel(), $offer->customer?->name ?? __('dinein.voucher_anonymous'), $offer->reason, $offer->sourceTable ? __('dinein.voucher_from_table', ['table' => $offer->sourceTable->label]) : null])->filter()->implode(' · ') }}</small>
                            <small>{{ __('dinein.voucher_meta', ['time' => $offer->created_at->format('H:i d/m'), 'name' => $offer->creator?->name ?? '—']) }}{{ $offer->expires_on ? ' · '.__('customers.offer_until', ['date' => $offer->expires_on->format('d/m/Y')]) : '' }}</small>
                        </span>
                        <span class="badge {{ $offer->statusBadge() }}">{{ $offer->statusLabel() }}</span>
                        @component('partials.kebab', ['label' => __('dinein.voucher_actions', ['code' => $offer->code])])
                            <button type="button" data-open-dialog="voucher-{{ $offer->id }}">{{ __('dinein.voucher_show') }}</button>
                            @if ($offer->used_at === null)
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
        @include('partials.pagination', ['paginator' => $offers])

        @foreach ($offers as $offer)
            @include('modules.dinein.voucher-dialog', ['offer' => $offer])
        @endforeach
        @include('partials.voucher-scripts')
    @endif
@endsection
