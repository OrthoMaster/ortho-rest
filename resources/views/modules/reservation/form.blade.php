@extends('layouts.admin')

@section('title', $reservation->exists ? __('reservation.edit') : __('reservation.add'))

@section('content')
    @include('partials.page-title', [
        'title' => $reservation->exists ? __('reservation.edit') : __('reservation.add'),
        'back' => $reservation->exists ? route('tenant.admin.reservation.show', $reservation) : back_url(route('tenant.admin.reservation.index')),
    ])

    @if ($areas->isEmpty())
        <p class="empty">{{ __('reservation.need_area') }}</p>
    @else
        <form method="post" class="panel narrow stack" data-guard
              action="{{ $reservation->exists ? route('tenant.admin.reservation.update', $reservation) : route('tenant.admin.reservation.store') }}">
            @csrf
            @if ($reservation->exists) @method('put') @endif
            @include('partials.field', ['name' => 'customer_name', 'label' => __('reservation.customer_name'), 'value' => $reservation->customer?->name])
            @include('partials.field', ['name' => 'customer_phone', 'type' => 'tel', 'label' => __('reservation.customer_phone'), 'value' => $reservation->customer?->phone])
            @include('partials.field', ['name' => 'customer_email', 'type' => 'email', 'label' => __('reservation.customer_email'), 'value' => $reservation->customer?->email, 'required' => false, 'hint' => __('reservation.email_hint')])
            @unless ($reservation->exists)
                @include('partials.select', ['name' => 'source', 'label' => __('reservation.source_label'), 'value' => 'phone', 'options' => [
                    'phone' => \App\Modules\Reservation\ReservationSource::Phone->label(),
                    'walk_in' => \App\Modules\Reservation\ReservationSource::WalkIn->label(),
                ]])
            @endunless
            <div class="form-row">
                @include('partials.field', ['name' => 'reserved_at', 'type' => 'datetime-local', 'label' => __('reservation.time'), 'value' => $reservation->reserved_at?->format('Y-m-d\TH:i')])
                @include('partials.field', ['name' => 'party_size', 'type' => 'number', 'label' => __('reservation.party_size'), 'value' => $reservation->party_size])
            </div>
            @include('partials.select', ['name' => 'area_id', 'label' => __('reservation.area'), 'value' => $reservation->area_id, 'options' => $areas->mapWithKeys(fn ($area) => [$area->id => $area->name.' ('.__('reservation.seats', ['count' => $area->capacity]).')'])])
            @include('partials.textarea', ['name' => 'notes', 'label' => __('reservation.notes'), 'value' => $reservation->notes])
            <button class="btn" type="submit">{{ __('reservation.save') }}</button>
        </form>
    @endif
@endsection
