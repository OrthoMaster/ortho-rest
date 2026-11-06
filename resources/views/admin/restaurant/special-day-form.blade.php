@extends('layouts.admin')

@php($editing = $day->exists)
@php($title = $editing ? __('special_days.edit_title', ['date' => $day->date->format('d/m/Y')]) : __('special_days.add'))

@section('title', $title)

@section('content')
    @include('partials.page-title', ['title' => $title, 'back' => route('tenant.admin.restaurant.special-days.index')])

    @php($type = old('type', $day->type->value))
    @php($closedKeys = old('closed_shifts', $day->type === \App\Enums\SpecialDayType::Closed ? $day->keys() : []))
    @php($openKeys = old('open_shifts', $day->type === \App\Enums\SpecialDayType::Open ? $day->keys() : []))
    @php($custom = $day->type === \App\Enums\SpecialDayType::Open ? collect($day->periods)->first(fn ($period) => ! $templates->has(\App\Models\SpecialDay::key($period['name'], $period['start'], $period['end']))) : null)
    @php($wholeDay = (bool) old('whole_day', $editing ? $day->isWholeDay() : true))

    <form method="post" action="{{ $editing ? route('tenant.admin.restaurant.special-days.update', $day) : route('tenant.admin.restaurant.special-days.store') }}" class="panel narrow stack" data-guard data-special-day>
        @csrf
        @if ($editing) @method('put') @endif

        @include('partials.date-picker', ['name' => 'date', 'label' => __('special_days.date'), 'value' => old('date', $day->date->toDateString()), 'min' => $editing ? null : today()->toDateString()])

        <div class="field">
            <span class="field-label">{{ __('special_days.kind') }}</span>
            <div class="segmented" role="radiogroup" aria-label="{{ __('special_days.kind') }}">
                @foreach (\App\Enums\SpecialDayType::cases() as $case)
                    <label><input type="radio" name="type" value="{{ $case->value }}" @checked($type === $case->value) data-day-type> <span>{{ $case->label() }}</span></label>
                @endforeach
            </div>
        </div>

        <div class="stack" data-type-section="closed" @if ($type !== 'closed') hidden @endif>
            <div class="field">
                <label class="check"><input type="checkbox" name="whole_day" value="1" data-reveal="whole_day" data-reveal-off @checked($wholeDay)> {{ __('special_days.close_whole_day') }}</label>
            </div>
            <div class="field" data-reveal-for="whole_day" @if ($wholeDay) hidden @endif>
                <span class="field-label">{{ __('special_days.closed_shifts') }}</span>
                <div class="toggles" data-closed-shifts data-shifts="{{ json_encode($weekdayShifts, JSON_UNESCAPED_UNICODE) }}" data-selected="{{ json_encode(array_values($closedKeys)) }}" data-empty="{{ __('special_days.no_shifts_that_day') }}"></div>
                @error('closed_shifts')<small class="error">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="stack" data-type-section="open" @if ($type !== 'open') hidden @endif>
            <div class="field">
                <span class="field-label">{{ __('special_days.open_shifts') }}</span>
                @if ($templates->isNotEmpty())
                    <div class="toggles">
                        @foreach ($templates as $key => $template)
                            <label class="check"><input type="checkbox" name="open_shifts[]" value="{{ $key }}" @checked(in_array($key, $openKeys, true))> {{ $template['name'] }} {{ $template['start'] }} – {{ $template['end'] }}</label>
                        @endforeach
                    </div>
                @endif
                <label class="check"><input type="checkbox" name="custom_enabled" value="1" data-reveal="custom_enabled" @checked(old('custom_enabled', $custom !== null))> {{ __('special_days.custom_shift') }}</label>
                @error('open_shifts')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="stack subpanel" data-reveal-for="custom_enabled" @unless (old('custom_enabled', $custom !== null)) hidden @endunless>
                @include('partials.field', ['name' => 'custom_name', 'label' => __('shifts.name'), 'value' => $custom['name'] ?? __('special_days.custom_default_name'), 'maxlength' => 50])
                @include('partials.time-picker', ['name' => 'custom_start', 'label' => __('shifts.start_time'), 'value' => $custom['start'] ?? '10:00', 'minuteStep' => 5])
                @include('partials.time-picker', ['name' => 'custom_end', 'label' => __('shifts.end_time'), 'value' => $custom['end'] ?? '14:00', 'minuteStep' => 5])
            </div>
        </div>

        @include('partials.field', ['name' => 'reason', 'label' => __('special_days.reason'), 'value' => $day->reason, 'maxlength' => 255, 'required' => false, 'hint' => __('special_days.reason_hint')])

        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
    </form>
@endsection
