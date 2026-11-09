@extends('layouts.admin')

@section('title', __('ui.nav.customers'))

@section('content')
    <h1>{{ __('ui.nav.customers') }}</h1>


    @if ($soon > 0)
        <p class="flash flash-warn">{{ __('customers.soon', ['count' => $soon, 'days' => \App\Support\CustomerRetention::NOTICE_DAYS]) }}</p>
    @endif

    <div class="list-tools">
        @can('customers.export')
            <a class="btn btn-quiet" href="{{ route('tenant.admin.customers.export') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/></svg>
                {{ __('customers.export') }}
            </a>
        @endcan
        <form class="list-search" method="get">
            <label>
                <span class="visually-hidden">{{ __('reservation.customer_search') }}</span>
                <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('reservation.customer_search') }}" autocomplete="off">
            </label>
        </form>
    </div>

    @if ($customers->isEmpty())
        <p class="empty">{{ __('reservation.customers_empty') }}</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('customers.code') }}</th>
                        <th>{{ __('reservation.customer_name') }}</th>
                        <th>{{ __('reservation.customer_phone') }}</th>
                        <th>{{ __('reservation.count_booked') }}</th>
                        <th>{{ __('reservation.count_cancelled') }}</th>
                        <th>{{ __('reservation.count_no_show') }}</th>
                        <th>{{ __('customers.delete_date') }}</th>
                        @can('customers.anonymize')<th><span class="visually-hidden">{{ __('reservation.area_actions') }}</span></th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr @class(['is-muted' => $customer->anonymized_at])>
                            <td class="customer-code">{{ $customer->code() }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>@if ($customer->phone)<a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>@else — @endif</td>
                            <td>{{ $customer->reservations_count }}</td>
                            <td>{{ $customer->cancelled_count }}</td>
                            <td>{{ $customer->no_show_count }}</td>
                            <td>
                                @if ($customer->anonymized_at)
                                    {{ __('customers.deleted_on', ['date' => $customer->anonymized_at->format('d/m/Y')]) }}
                                @else
                                    {{ \App\Support\CustomerRetention::deleteDate($restaurant, $customer->last_seen_at)->format('d/m/Y') }}
                                @endif
                            </td>
                            @can('customers.anonymize')
                                <td class="col-tools">
                                    @unless ($customer->anonymized_at)
                                        @component('partials.kebab', ['label' => __('customers.actions_for', ['name' => $customer->name])])
                                            <form method="post" action="{{ route('tenant.admin.customers.anonymize', $customer) }}"
                                                  data-confirm="{{ __('customers.anonymize_confirm', ['name' => $customer->name]) }}"
                                                  data-confirm-title="{{ __('customers.anonymize_title') }}" data-confirm-ok="{{ __('customers.anonymize') }}" data-confirm-danger>
                                                @csrf
                                                <button class="is-danger" type="submit">{{ __('customers.anonymize') }}</button>
                                            </form>
                                        @endcomponent
                                    @endunless
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $customers->links('partials.pagination') }}
    @endif
@endsection
