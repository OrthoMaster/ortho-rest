@extends('layouts.admin')

@php($editing = $shift->exists)
@php($title = $editing ? __('shifts.edit_title', ['name' => $shift->name, 'day' => __('ui.calendar.weekdays')[$shift->weekday - 1]]) : __('shifts.add').': '.$type->label())

@section('title', $title)

@section('content')
    @include('partials.page-title', ['title' => $title, 'back' => route('tenant.admin.restaurant.shifts.index', $type)])

    <form method="post" action="{{ $editing ? route('tenant.admin.restaurant.shifts.update', $shift) : route('tenant.admin.restaurant.shifts.store', $type) }}" class="panel narrow stack" data-guard>
        @csrf
        @if ($editing) @method('put') @endif

        @include('partials.field', ['name' => 'name', 'label' => __('shifts.name'), 'value' => $shift->name, 'maxlength' => 50, 'hint' => __('shifts.name_hint_'.$type->value)])

        @include('partials.time-picker', ['name' => 'start_time', 'label' => __('shifts.start_time'), 'value' => $shift->start_time])
        @include('partials.time-picker', ['name' => 'end_time', 'label' => __('shifts.end_time'), 'value' => $shift->end_time, 'hint' => __('shifts.overnight_hint')])

        @unless ($editing)
            <div class="field">
                <span class="field-label">{{ __('shifts.weekdays') }}</span>
                <div class="toggles">
                    @foreach (__('ui.calendar.weekdays') as $index => $dayName)
                        <label class="check"><input type="checkbox" name="weekdays[]" value="{{ $index + 1 }}" @checked(in_array($index + 1, array_map('intval', $selectedDays), true))> {{ $dayName }}</label>
                    @endforeach
                </div>
                @error('weekdays')<small class="error">{{ $message }}</small>@enderror
            </div>
        @endunless

        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
    </form>
@endsection
