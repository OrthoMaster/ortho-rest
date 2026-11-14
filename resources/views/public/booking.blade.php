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
    <section class="booking-card is-form">
        <header class="booking-hero">
            <a class="booking-logo" href="{{ tenant_route('public.home') }}">
                @include('partials.restaurant-logo', ['restaurant' => $restaurant, 'size' => 'lg'])
            </a>
            <h1>{{ __('website.booking.title') }}</h1>
            <p class="lead">{{ __('website.booking.lead') }}</p>
        </header>

        <form data-liresto-booking class="booking-form">
            <div class="booking-step">
                <h2><span class="booking-step-no">1</span>{{ __('website.booking.step_when') }}</h2>
                <div data-liresto-when></div>
            </div>

            <div class="booking-step">
                <h2><span class="booking-step-no">2</span>{{ __('website.booking.your_info') }}</h2>
                <div class="booking-fields">
                    <div class="field">
                        <label for="b-name">{{ __('website.fields.name') }}@include('partials.required')</label>
                        <input id="b-name" name="name" required maxlength="100" autocomplete="name" placeholder="{{ __('website.booking.name_placeholder') }}">
                    </div>
                    <div class="field">
                        <label for="b-phone">{{ __('website.fields.phone') }}@include('partials.required')</label>
                        <input id="b-phone" name="phone" type="tel" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="{{ __('website.booking.phone_placeholder') }}">
                    </div>
                    <div class="field is-wide">
                        <label for="b-email">{{ __('website.fields.email') }}</label>
                        <input id="b-email" name="email" type="email" maxlength="150" autocomplete="email" placeholder="{{ __('website.booking.email_placeholder') }}">
                    </div>
                    <div class="field is-wide">
                        <label for="b-notes">{{ __('website.fields.notes') }}</label>
                        <textarea id="b-notes" name="notes" rows="3" maxlength="500"></textarea>
                    </div>
                </div>
            </div>

            <div data-liresto-result hidden></div>
            <div class="booking-submit">
                <button class="btn" type="submit">{{ __($confirm ? 'website.booking.submit' : 'website.booking.submit_direct') }}</button>
                @if ($confirm)<small>{{ __('website.booking.confirm_note') }}</small>@endif
            </div>
        </form>
    </section>

    @include('public.booking-contact', ['restaurant' => $restaurant, 'hours' => $hours])
</main>
@endsection
