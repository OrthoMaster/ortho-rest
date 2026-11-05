@extends('layouts.admin')

@section('title', __('reservation.settings'))

@section('content')
    <h1>{{ __('modules.reservation') }}</h1>

    @include('modules.reservation.subnav')

    <section class="panel">
        <h2>{{ __('reservation.areas') }}</h2>
        @if ($areas->isNotEmpty())
            <ul class="rows">
                @foreach ($areas as $area)
                    <li>
                        <form method="post" action="{{ route('tenant.admin.reservation.settings.areas.update', $area) }}" class="inline-form area-form">
                            @csrf
                            @method('put')
                            <input name="name" value="{{ $area->name }}" aria-label="{{ __('reservation.area_name') }}" required>
                            <input class="input-short" type="number" name="capacity" min="1" value="{{ $area->capacity }}" aria-label="{{ __('reservation.area_capacity') }}" required>
                            <label class="check"><input type="checkbox" name="online_bookable" value="1" @checked($area->online_bookable)> {{ __('reservation.area_online') }}</label>
                            <label class="check"><input type="checkbox" name="is_active" value="1" @checked($area->is_active)> {{ __('reservation.area_active') }}</label>
                            <button class="btn btn-quiet btn-sm" type="submit">{{ __('ui.common.save') }}</button>
                        </form>
                        <form method="post" action="{{ route('tenant.admin.reservation.settings.areas.destroy', $area) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('reservation.area_delete_confirm', ['name' => $area->name]) }}">
                            @csrf
                            @method('delete')
                            <button class="link link-danger" type="submit">{{ __('reservation.area_delete') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('tenant.admin.reservation.settings.areas.store') }}" class="inline-form">
            @csrf
            <input name="name" placeholder="{{ __('reservation.area_example') }}" aria-label="{{ __('reservation.area_name') }}" required>
            <input class="input-short" type="number" name="capacity" min="1" placeholder="{{ __('reservation.area_capacity') }}" aria-label="{{ __('reservation.area_capacity') }}" required>
            <input type="hidden" name="online_bookable" value="1">
            <input type="hidden" name="is_active" value="1">
            <button class="btn btn-quiet" type="submit">{{ __('reservation.area_add') }}</button>
        </form>
        <p class="row-note">{{ __('reservation.area_hint') }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('reservation.hours') }}</h2>
        @php($hours = $restaurant->opening_hours)
        <form method="post" action="{{ route('tenant.admin.reservation.settings.hours') }}" class="stack" data-guard>
            @csrf
            @method('put')
            <div class="hours">
                @foreach (range(1, 7) as $day)
                    @php($row = $hours === null ? ['opens' => '10:00', 'closes' => '22:00'] : ($hours[$day] ?? null))
                    <div class="hours-row">
                        <label class="check"><input type="checkbox" name="hours[{{ $day }}][open]" value="1" @checked($row !== null)> {{ __('reservation.weekday.'.$day) }}</label>
                        <input type="time" name="hours[{{ $day }}][opens]" value="{{ $row['opens'] ?? '10:00' }}" aria-label="{{ __('reservation.opens') }}">
                        <input type="time" name="hours[{{ $day }}][closes]" value="{{ $row['closes'] ?? '22:00' }}" aria-label="{{ __('reservation.closes') }}">
                        @error("hours.$day.closes")<small class="error">{{ $message }}</small>@enderror
                    </div>
                @endforeach
            </div>
            <button class="btn" type="submit">{{ __('reservation.save_hours') }}</button>
        </form>
    </section>

    <section class="panel">
        <h2>{{ __('reservation.closed_dates') }}</h2>
        @if ($restaurant->closed_dates)
            <ul class="rows">
                @foreach ($restaurant->closed_dates as $closed)
                    <li>
                        <span class="row-name">{{ \Carbon\Carbon::parse($closed['date'])->format('d/m/Y') }}</span>
                        @if ($closed['note'] ?? null)<span class="row-note">{{ $closed['note'] }}</span>@endif
                        <form method="post" action="{{ route('tenant.admin.reservation.settings.closed-dates.destroy', $closed['date']) }}">
                            @csrf
                            @method('delete')
                            <button class="link link-danger" type="submit">{{ __('reservation.area_delete') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('tenant.admin.reservation.settings.closed-dates.store') }}" class="inline-form">
            @csrf
            <input class="input-date" type="date" name="date" aria-label="{{ __('reservation.date') }}" required>
            <input name="note" placeholder="{{ __('reservation.closed_note') }}" aria-label="{{ __('reservation.closed_note') }}">
            <button class="btn btn-quiet" type="submit">{{ __('reservation.closed_add') }}</button>
        </form>
    </section>

    <section class="panel">
        <h2>{{ __('reservation.booking_rules') }}</h2>
        <form method="post" action="{{ route('tenant.admin.reservation.settings.booking') }}" class="stack narrow" data-guard>
            @csrf
            @method('put')
            @include('partials.field', ['name' => 'turn_minutes', 'type' => 'number', 'label' => __('reservation.turn_minutes'), 'value' => $settings['turn_minutes']])
            @include('partials.field', ['name' => 'min_notice_minutes', 'type' => 'number', 'label' => __('reservation.min_notice_minutes'), 'value' => $settings['min_notice_minutes']])
            @include('partials.field', ['name' => 'max_advance_days', 'type' => 'number', 'label' => __('reservation.max_advance_days'), 'value' => $settings['max_advance_days']])
            @include('partials.field', ['name' => 'slot_interval_minutes', 'type' => 'number', 'label' => __('reservation.slot_interval_minutes'), 'value' => $settings['slot_interval_minutes']])
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </form>
    </section>
@endsection
