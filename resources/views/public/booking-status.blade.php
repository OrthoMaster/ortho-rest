{{-- Khách xem đặt bàn của mình qua link riêng: trạng thái, chi tiết, tự huỷ khi còn được. --}}
@extends('layouts.base')

@section('title', __('website.status.title'))
@section('body_class', 'public-page')

@section('body')
<main class="booking-page">
    @include('partials.flash')

    <section class="booking-card stack">
        <header class="booking-hero">
            <a class="booking-logo" href="{{ tenant_route('public.home') }}">
                @include('partials.restaurant-logo', ['restaurant' => $restaurant, 'size' => 'lg'])
            </a>
        </header>
        <div class="booking-status-head">
            <h1>{{ __('website.status.title') }}</h1>
            <span class="badge {{ $reservation->status->badge() }}">{{ $reservation->status->label() }}</span>
        </div>
        <p class="lead">{{ __('website.status.states.'.$reservation->status->value) }}</p>

        <dl class="booking-facts">
            <div><dt>{{ __('website.status.code') }}</dt><dd><strong>{{ $reservation->code() }}</strong></dd></div>
            <div><dt>{{ __('website.status.when') }}</dt><dd>{{ __('ui.calendar.weekdays')[$reservation->reserved_at->dayOfWeekIso - 1] }} {{ $reservation->reserved_at->format('d/m/Y') }}, <strong>{{ $reservation->reserved_at->format('H:i') }}</strong></dd></div>
            <div><dt>{{ __('website.widget.party') }}</dt><dd>{{ __('website.status.party', ['count' => $reservation->party_size]) }}</dd></div>
            @if ($reservation->area)<div><dt>{{ __('website.status.area') }}</dt><dd>{{ $reservation->area->name }}</dd></div>@endif
            <div><dt>{{ __('website.status.name') }}</dt><dd>{{ $reservation->customer?->name }}</dd></div>
            @if ($reservation->notes)<div><dt>{{ __('website.status.notes') }}</dt><dd>{{ $reservation->notes }}</dd></div>@endif
        </dl>

        @if ($reservation->guestCanCancel())
            <p class="row-note">{{ __('website.status.change_hint') }}@if ($restaurant->phone) <a href="tel:{{ preg_replace('/[^0-9+]/', '', $restaurant->phone) }}">{{ $restaurant->phone }}</a>@endif</p>
            <form method="post" action="{{ tenant_route('public.booking.cancel', ['token' => $reservation->public_token]) }}"
                  data-confirm="{{ __('website.status.cancel_confirm') }}" data-confirm-title="{{ __('website.status.cancel_title') }}"
                  data-confirm-ok="{{ __('website.status.cancel') }}" data-confirm-danger>
                @csrf
                <button class="btn btn-danger" type="submit">{{ __('website.status.cancel') }}</button>
            </form>
        @else
            <p><a class="btn btn-quiet" href="{{ tenant_route('public.booking') }}">{{ __('website.status.book_again') }}</a></p>
        @endif
    </section>

    @include('public.booking-contact', ['restaurant' => $restaurant])
</main>
@include('partials.confirm-dialog')
@endsection
