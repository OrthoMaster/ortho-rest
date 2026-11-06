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
        {{-- Từng bước: ngày, giờ, số khách → kiểm tra chỗ còn → khu vực (không bắt buộc) → thông tin khách. --}}
        <form method="post" class="stack reservation-form" data-guard
              data-availability="{{ route('tenant.admin.reservation.availability') }}" @if ($editing) data-ignore="{{ $reservation->id }}" @endif
              data-checking="{{ __('reservation.checking') }}" data-check-failed="{{ __('reservation.check_failed') }}"
              action="{{ $editing ? route('tenant.admin.reservation.update', $reservation) : route('tenant.admin.reservation.store') }}">
            @csrf
            @if ($editing) @method('put') @endif

            <div class="reservation-grid">
                <div class="stack">
                    <section class="panel stack">
                        <h2>{{ __('reservation.when_section') }}</h2>
                        @include('partials.date-picker', ['name' => 'reserved_date', 'label' => __('reservation.date'), 'required' => true,
                            'value' => $reservation->reserved_at->toDateString(),
                            'min' => $editing ? min($reservation->reserved_at->toDateString(), today()->toDateString()) : today()->toDateString()])
                        @include('partials.time-picker', ['name' => 'reserved_time', 'label' => __('reservation.time_of_day'), 'required' => true,
                            'value' => $reservation->reserved_at->format('H:i')])

                        @include('partials.number-stepper', ['name' => 'party_size', 'label' => __('reservation.party_size'), 'required' => true,
                            'value' => $reservation->party_size, 'min' => 1, 'max' => 1000, 'suffix' => __('reservation.unit_guests')])

                        <div class="availability" data-availability-status role="status" aria-live="polite" hidden>
                            <span data-availability-text></span>
                            <small data-availability-warning hidden></small>
                        </div>
                        @error('reserved_at')<small class="error">{{ $message }}</small>@enderror
                    </section>

                    <section class="panel stack" data-after-check>
                        <div>
                            <h2>{{ __('reservation.area') }}</h2>
                            <small class="section-hint">{{ __('reservation.area_optional_hint') }}</small>
                        </div>
                        @php($areaValue = (string) old('area_id', $reservation->area_id))
                        <div class="choice-list" role="radiogroup" aria-label="{{ __('reservation.area') }}">
                            <label class="choice">
                                <input type="radio" name="area_id" value="" @checked($areaValue === '')>
                                <span>{{ __('reservation.no_area') }}</span>
                            </label>
                            @foreach ($areas as $area)
                                <label class="choice" data-area-choice="{{ $area->id }}">
                                    <input type="radio" name="area_id" value="{{ $area->id }}" @checked($areaValue === (string) $area->id)>
                                    <span>{{ $area->name }}</span>
                                    <small data-area-left>{{ __('reservation.seats', ['count' => $area->capacity]) }}</small>
                                </label>
                            @endforeach
                        </div>
                        @error('area_id')<small class="error">{{ $message }}</small>@enderror
                    </section>
                </div>

                <section class="panel stack" data-after-check>
                    <h2>{{ __('reservation.customer_section') }}</h2>
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
                    @include('partials.field', ['name' => 'customer_name', 'label' => __('reservation.customer_name'), 'value' => $reservation->customer?->name, 'maxlength' => 255, 'autocomplete' => 'off'])
                    @include('partials.field', ['name' => 'customer_phone', 'type' => 'tel', 'label' => __('reservation.customer_phone'), 'value' => $reservation->customer?->phone, 'autocomplete' => 'off', 'required' => false])
                    @include('partials.field', ['name' => 'customer_email', 'type' => 'email', 'label' => __('reservation.customer_email'), 'value' => $reservation->customer?->email, 'required' => false, 'hint' => __('reservation.email_hint')])
                    @include('partials.textarea', ['name' => 'notes', 'label' => __('reservation.notes'), 'value' => $reservation->notes, 'maxlength' => 1000, 'placeholder' => __('reservation.notes_example')])
                </section>
            </div>

            <div data-after-check>
                <button class="btn" type="submit">{{ __('reservation.save') }}</button>
            </div>
        </form>
    @endif
@endsection
