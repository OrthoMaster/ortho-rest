{{-- Trang đặt bàn mẫu của Liresto: theo màu, logo quán. Bộ chọn ngày giờ và việc gửi do booking-widget.js làm. --}}
@extends('layouts.base')

@section('title', __('website.booking.title'))
@section('body_class', 'public-page')

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/booking-widget.css') }}">
    <script>window.LIRESTO_BOOKING = @json($config);</script>
    <script src="{{ versioned_asset('js/booking-widget.js') }}" defer></script>
@endpush

@section('body')
<main class="booking-page">
    <header class="booking-head">
        <a class="booking-brand" href="{{ tenant_route('public.home') }}">
            @include('partials.restaurant-logo', ['restaurant' => $restaurant, 'size' => 'lg'])
            <span>{{ $restaurant->name }}</span>
        </a>
        <ul class="booking-contact">
            @if ($restaurant->address)<li>{{ $restaurant->address }}</li>@endif
            @if ($restaurant->phone)<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $restaurant->phone) }}">{{ $restaurant->phone }}</a></li>@endif
            <li>{{ __('website.booking.hours') }}: {{ $hours }}</li>
        </ul>
    </header>

    <section class="panel booking-card">
        <h1>{{ __('website.booking.title') }}</h1>
        <p class="lead">{{ __('website.booking.lead') }}</p>

        <form data-liresto-booking class="stack">
            <div data-liresto-when></div>

            <h2>{{ __('website.booking.your_info') }}</h2>
            <div class="field">
                <label for="b-name">{{ __('website.fields.name') }}@include('partials.required')</label>
                <input id="b-name" name="name" required maxlength="100" autocomplete="name" placeholder="{{ __('website.booking.name_placeholder') }}">
            </div>
            <div class="field">
                <label for="b-phone">{{ __('website.fields.phone') }}@include('partials.required')</label>
                <input id="b-phone" name="phone" type="tel" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="{{ __('website.booking.phone_placeholder') }}">
            </div>
            <div class="field">
                <label for="b-email">{{ __('website.fields.email') }}</label>
                <input id="b-email" name="email" type="email" maxlength="150" autocomplete="email" placeholder="{{ __('website.booking.email_placeholder') }}">
            </div>
            <div class="field">
                <label for="b-notes">{{ __('website.fields.notes') }}</label>
                <textarea id="b-notes" name="notes" rows="3" maxlength="500" placeholder="{{ __('website.booking.notes_placeholder') }}"></textarea>
            </div>
            <div data-liresto-result hidden></div>
            <div class="booking-submit">
                <button class="btn" type="submit">{{ __($confirm ? 'website.booking.submit' : 'website.booking.submit_direct') }}</button>
                @if ($confirm)<small>{{ __('website.booking.confirm_note') }}</small>@endif
            </div>
        </form>
    </section>
</main>
@endsection
