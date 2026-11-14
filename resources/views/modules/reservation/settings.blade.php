@extends('layouts.admin')

@section('title', __('reservation.settings'))

@section('content')
    <h1>{{ __('modules.reservation') }}</h1>

    @include('modules.reservation.subnav')

    <form method="post" action="{{ route('tenant.admin.reservation.settings.booking') }}" class="stack settings-stack" data-guard>
        @csrf
        @method('put')

        @php($online = (bool) old('online_enabled', $settings['online_enabled']))
        <section class="panel stack">
            <div class="panel-head">
                <h2 id="online-title">{{ __('reservation.online') }}</h2>
                <input type="checkbox" name="online_enabled" value="1" aria-labelledby="online-title" data-reveal="online" @checked($online)>
            </div>
            <p class="row-note">{{ __('reservation.online_hint') }}</p>
        </section>

        <section class="panel stack" data-reveal-for="online" @unless ($online) hidden @endunless>
            <h2>{{ __('reservation.online_rules') }}</h2>
            @include('partials.number-stepper', ['name' => 'turn_minutes', 'label' => __('reservation.turn_label'), 'value' => $settings['turn_minutes'],
                'min' => 15, 'max' => 600, 'step' => 15, 'suffix' => __('reservation.unit_minutes'), 'hint' => __('reservation.turn_hint')])
            @include('partials.number-stepper', ['name' => 'min_notice_days', 'label' => __('reservation.min_notice_days'), 'value' => $settings['min_notice_days'],
                'min' => 0, 'max' => 60, 'suffix' => __('reservation.unit_days'), 'hint' => __('reservation.min_notice_hint')])
            @include('partials.number-stepper', ['name' => 'max_advance_days', 'label' => __('reservation.max_advance_label'), 'value' => $settings['max_advance_days'],
                'min' => 1, 'max' => 365, 'suffix' => __('reservation.unit_days')])
            <div class="field">
                <label class="check"><input type="checkbox" name="require_confirmation" value="1" @checked(old('require_confirmation', ! $settings['auto_confirm']))> {{ __('reservation.require_confirmation') }}</label>
                <small>{{ __('reservation.require_confirmation_hint') }}</small>
            </div>
        </section>

        <section class="panel stack" data-reveal-for="online" @unless ($online) hidden @endunless>
            <h2>{{ __('reservation.online_seats') }}</h2>
            @if ($totalChangedFrom !== null)
                <p class="flash flash-warn" role="status">{{ __('reservation.total_changed_since', ['from' => $totalChangedFrom, 'to' => $total]) }}</p>
            @endif
            @if ($total === 0)
                <p class="row-note">{{ __('reservation.need_area_seats') }} <a href="{{ route('tenant.admin.restaurant.capacity.index') }}">{{ __('ui.restaurant.title') }} → {{ __('reservation.capacity') }}</a></p>
            @else
                @include('partials.number-stepper', ['name' => 'online_seats', 'label' => __('reservation.online_seats_label'), 'value' => $onlineSeats,
                    'min' => 1, 'max' => $total, 'suffix' => __('reservation.of_total', ['total' => $total]), 'hint' => __('reservation.online_seats_hint')])
            @endif
        </section>

        <section class="panel stack">
            <h2>{{ __('reservation.hours') }}</h2>
            <p class="row-note">{{ __('reservation.hours_moved') }}</p>
            @can('settings.manage')
                <div class="inline-form">
                    <a class="btn btn-quiet" href="{{ route('tenant.admin.restaurant.shifts.index', \App\Enums\ShiftType::Opening) }}">{{ __('reservation.hours_open') }}</a>
                    <a class="btn btn-quiet" href="{{ route('tenant.admin.restaurant.special-days.index') }}">{{ __('special_days.title') }}</a>
                </div>
            @endcan
        </section>

        <div>
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </div>
    </form>
@endsection
