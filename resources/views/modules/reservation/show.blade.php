@extends('layouts.admin')

@section('title', $reservation->customer->name)

@section('content')
    <div class="page-head">
        @component('partials.page-title', ['title' => $reservation->customer->name, 'back' => back_url($reservation->reserved_at->isToday() || ! auth()->user()->can('reservation.manage')
            ? route('tenant.admin.reservation.index')
            : route('tenant.admin.reservation.manage', ['upcoming' => 0, 'from' => $reservation->reserved_at->toDateString(), 'to' => $reservation->reserved_at->toDateString()]))])
            <span class="badge {{ $reservation->status->badge() }}">{{ $reservation->status->label() }}</span>
        @endcomponent
        @if ($reservation->status->holdsSeats() && auth()->user()->can('reservation.create'))
            <a class="btn btn-quiet" href="{{ route('tenant.admin.reservation.edit', $reservation) }}">{{ __('reservation.edit') }}</a>
        @endif
    </div>

    @error('status')<p class="flash flash-error">{{ $message }}</p>@enderror

    @if ($reservation->status->nextFor(auth()->user()))
        <section class="panel">
            @include('modules.reservation.status-actions')
        </section>
    @endif

    <section class="panel">
        <dl class="details">
            <dt>{{ __('reservation.time') }}</dt>
            <dd>{{ $reservation->reserved_at->format('H:i d/m/Y') }}</dd>
            <dt>{{ __('reservation.party_size') }}</dt>
            <dd>{{ $reservation->party_size }}</dd>
            <dt>{{ __('reservation.area') }}</dt>
            <dd>{{ $reservation->area?->name ?? __('reservation.no_area') }}</dd>
            @if ($reservation->diningTable)
                <dt>{{ __('reservation.table') }}</dt>
                <dd>
                    @if ($reservation->diningTable->isOpen() && auth()->user()->can('dinein.order'))
                        <a href="{{ route('tenant.admin.dinein.tables.show', $reservation->diningTable->label) }}">{{ $reservation->diningTable->label }}</a>
                    @else
                        {{ $reservation->diningTable->label }}
                    @endif
                </dd>
            @endif
            <dt>{{ __('customers.code') }}</dt>
            <dd>{{ $reservation->customer->code() }}</dd>
            @if ($reservation->customer->phone)
                <dt>{{ __('reservation.customer_phone') }}</dt>
                <dd><a href="tel:{{ $reservation->customer->phone }}">{{ $reservation->customer->phone }}</a></dd>
            @endif
            @if ($reservation->customer->email)
                <dt>{{ __('reservation.customer_email') }}</dt>
                <dd>{{ $reservation->customer->email }}</dd>
            @endif
            <dt>{{ __('reservation.source_label') }}</dt>
            <dd>{{ $reservation->source->label() }}</dd>
            <dt>{{ __('reservation.created_by') }}</dt>
            <dd>{{ $reservation->creator?->name ?? __('reservation.created_by_customer') }}, {{ $reservation->created_at->format('H:i d/m/Y') }}</dd>
            @if ($reservation->notes)
                <dt>{{ __('reservation.notes') }}</dt>
                <dd>{{ $reservation->notes }}</dd>
            @endif
        </dl>
    </section>

    <section class="panel">
        <h2>{{ __('reservation.history') }}</h2>
        <ol class="timeline">
            @foreach ($history as $entry)
                <li>
                    <strong>{{ $entry['title'] }}</strong>
                    @foreach ($entry['lines'] as $line)
                        <span class="edit-line">{{ $line }}</span>
                    @endforeach
                    <small>{{ $entry['user']?->name ?? ($reservation->source === \App\Modules\Reservation\ReservationSource::Online ? __('reservation.created_by_customer') : __('reservation.by_system')) }}, {{ $entry['at']->format('H:i d/m/Y') }}</small>
                </li>
            @endforeach
        </ol>
    </section>

    @include('modules.reservation.overbook-prompt')
    @include('modules.reservation.seat-dialog')
@endsection
