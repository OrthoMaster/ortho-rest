@extends('layouts.admin')

@php($editing = $reservation->exists)

@section('title', $editing ? __('reservation.edit') : __('reservation.add'))

@section('content')
    @include('partials.page-title', [
        'title' => $editing ? __('reservation.edit') : __('reservation.add'),
        'back' => $editing ? route('tenant.admin.reservation.show', $reservation) : back_url(route('tenant.admin.reservation.index')),
    ])

    @if ($areas->isEmpty())
        <p class="empty">{{ __('reservation.need_area') }}</p>
    @else
        <form method="post" class="stack reservation-form" data-guard
              action="{{ $editing ? route('tenant.admin.reservation.update', $reservation) : route('tenant.admin.reservation.store') }}">
            @csrf
            @if ($editing) @method('put') @endif

            <section class="panel stack">
                <h2>{{ __('reservation.booking_section') }}</h2>
                @unless ($editing)
                    <div class="field">
                        <span class="field-label">{{ __('reservation.source_label') }}@include('partials.required')</span>
                        <div class="segmented" role="radiogroup" aria-label="{{ __('reservation.source_label') }}">
                            @foreach ([\App\Modules\Reservation\ReservationSource::Phone, \App\Modules\Reservation\ReservationSource::WalkIn] as $source)
                                <label><input type="radio" name="source" value="{{ $source->value }}" @checked(old('source', 'phone') === $source->value)> <span>{{ $source->label() }}</span></label>
                            @endforeach
                        </div>
                        <small>{{ __('reservation.source_hint') }}</small>
                        @error('source')<small class="error">{{ $message }}</small>@enderror
                    </div>
                @endunless

                <div class="form-row">
                    @include('partials.date-picker', ['name' => 'reserved_date', 'label' => __('reservation.date'), 'required' => true,
                        'value' => $reservation->reserved_at->toDateString(),
                        'min' => $editing ? min($reservation->reserved_at->toDateString(), today()->toDateString()) : today()->toDateString()])
                    @include('partials.time-picker', ['name' => 'reserved_time', 'label' => __('reservation.time_of_day'), 'required' => true,
                        'value' => $reservation->reserved_at->format('H:i')])
                </div>
                @error('reserved_at')<small class="error">{{ $message }}</small>@enderror

                @include('partials.number-stepper', ['name' => 'party_size', 'label' => __('reservation.party_size'), 'required' => true,
                    'value' => $reservation->party_size, 'min' => 1, 'max' => 1000, 'suffix' => __('reservation.unit_guests')])

                @include('partials.select', ['name' => 'area_id', 'label' => __('reservation.area'), 'value' => $reservation->area_id,
                    'options' => $areas->mapWithKeys(fn ($area) => [$area->id => $area->name.' ('.__('reservation.seats', ['count' => $area->capacity]).')'])])
            </section>

            <section class="panel stack">
                <h2>{{ __('reservation.customer_section') }}</h2>
                @include('partials.field', ['name' => 'customer_name', 'label' => __('reservation.customer_name'), 'value' => $reservation->customer?->name, 'maxlength' => 255, 'autocomplete' => 'off'])
                @include('partials.field', ['name' => 'customer_phone', 'type' => 'tel', 'label' => __('reservation.customer_phone'), 'value' => $reservation->customer?->phone, 'autocomplete' => 'off'])
                @include('partials.field', ['name' => 'customer_email', 'type' => 'email', 'label' => __('reservation.customer_email'), 'value' => $reservation->customer?->email, 'required' => false, 'hint' => __('reservation.email_hint')])
                @include('partials.textarea', ['name' => 'notes', 'label' => __('reservation.notes'), 'value' => $reservation->notes, 'maxlength' => 1000, 'placeholder' => __('reservation.notes_example')])
            </section>

            <div>
                <button class="btn" type="submit">{{ __('reservation.save') }}</button>
            </div>
        </form>
    @endif
@endsection
