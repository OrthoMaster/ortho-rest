@extends('layouts.admin')

@section('title', __('ui.restaurant.title'))

@section('content')
    <h1>{{ __('ui.restaurant.title') }}</h1>

    @include('admin.restaurant.subnav')

    <div class="cols cols-2">
    <section class="panel">
        <h2>{{ __('ui.logo.title') }}</h2>
        @include('partials.logo-form', [
            'restaurant' => $restaurant,
            'updateUrl' => route('tenant.admin.restaurant.logo.update'),
            'destroyUrl' => route('tenant.admin.restaurant.logo.destroy'),
        ])
    </section>

    <section class="panel">
        <form method="post" action="{{ route('tenant.admin.restaurant.customer-data') }}" class="stack" data-guard>
            @csrf
            @method('put')
            <h2>{{ __('customers.retention_title') }}</h2>
            <div class="field">
                <span class="field-label">{{ __('customers.retention_label') }}</span>
                <div class="segmented" role="radiogroup" aria-label="{{ __('customers.retention_label') }}">
                    @foreach (\App\Support\CustomerRetention::DAYS as $days)
                        <label><input type="radio" name="customer_retention_days" value="{{ $days }}" @checked((int) old('customer_retention_days', \App\Support\CustomerRetention::days($restaurant)) === $days)> <span>{{ __('customers.retention_option', ['days' => $days]) }}</span></label>
                    @endforeach
                </div>
                <small>{{ __('customers.retention_hint') }}</small>
                @error('customer_retention_days')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div>
                <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
            </div>
        </form>
    </section>

    @if (has_module('kitchen'))
        {{-- Chuông báo món: chọn một trong ba tiếng, bấm Nghe thử để nghe trước. --}}
        @php($sound = \App\Modules\Kitchen\Kitchen::sound())
        <section class="panel">
            <form method="post" action="{{ route('tenant.admin.restaurant.sound') }}" class="stack" data-guard>
                @csrf
                @method('put')
                <div>
                    <h2>{{ __('kitchen.sound_title') }}</h2>
                    <small class="row-note">{{ __('kitchen.sound_lead') }}</small>
                </div>
                <div class="choice-list" role="radiogroup" aria-label="{{ __('kitchen.sound_title') }}">
                    @foreach (\App\Modules\Kitchen\Kitchen::SOUNDS as $option)
                        <label class="choice">
                            <input type="radio" name="sound" value="{{ $option }}" @checked(old('sound', $sound) === $option)>
                            <span>{{ __('kitchen.sounds.'.$option) }}</span>
                            <button class="btn btn-quiet btn-sm" type="button" data-sound-preview="{{ $option }}">{{ __('kitchen.sound_test') }}</button>
                        </label>
                    @endforeach
                </div>
                <div>
                    <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                </div>
            </form>
        </section>
    @endif
    </div>
@endsection
