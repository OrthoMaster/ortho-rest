@extends('layouts.admin')

@section('title', __('reservation.customers'))

@section('content')
    <h1>{{ __('modules.reservation') }}</h1>

    @include('modules.reservation.subnav')

    <form class="search" method="get">
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('reservation.customer_search') }}" aria-label="{{ __('reservation.customer_search') }}">
        <button class="btn btn-quiet" type="submit">{{ __('ui.super.search_submit') }}</button>
    </form>

    @if ($customers->isEmpty())
        <p class="empty">{{ __('reservation.customers_empty') }}</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('reservation.customer_name') }}</th>
                        <th>{{ __('reservation.customer_phone') }}</th>
                        <th>{{ __('reservation.count_booked') }}</th>
                        <th>{{ __('reservation.count_cancelled') }}</th>
                        <th>{{ __('reservation.count_no_show') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td><a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a></td>
                            <td>{{ $customer->reservations_count }}</td>
                            <td>{{ $customer->cancelled_count }}</td>
                            <td>{{ $customer->no_show_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $customers->links('partials.pagination') }}
    @endif
@endsection
