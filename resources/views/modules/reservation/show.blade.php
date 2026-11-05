@extends('layouts.admin')

@section('title', $reservation->customer->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $reservation->customer->name }}</h1>
            <span class="badge {{ $reservation->status->badge() }}">{{ $reservation->status->label() }}</span>
        </div>
        @if ($reservation->status->holdsSeats())
            <a class="btn btn-quiet" href="{{ route('tenant.admin.reservation.edit', $reservation) }}">{{ __('reservation.edit') }}</a>
        @endif
    </div>

    @error('status')<p class="flash flash-error">{{ $message }}</p>@enderror

    @if ($reservation->status->next())
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
            <dd>{{ $reservation->area->name }}</dd>
            <dt>{{ __('reservation.customer_phone') }}</dt>
            <dd><a href="tel:{{ $reservation->customer->phone }}">{{ $reservation->customer->phone }}</a></dd>
            @if ($reservation->customer->email)
                <dt>{{ __('reservation.customer_email') }}</dt>
                <dd>{{ $reservation->customer->email }}</dd>
            @endif
            <dt>{{ __('reservation.source_label') }}</dt>
            <dd>{{ $reservation->source->label() }}</dd>
            <dt>{{ __('reservation.created_by') }}</dt>
            <dd>{{ $reservation->creator?->name ?? __('reservation.created_by_customer') }}</dd>
            @if ($reservation->notes)
                <dt>{{ __('reservation.notes') }}</dt>
                <dd>{{ $reservation->notes }}</dd>
            @endif
        </dl>
    </section>

    <section class="panel">
        <h2>{{ __('reservation.history') }}</h2>
        <ul class="rows">
            @foreach ($reservation->statusLogs as $log)
                <li>
                    <span class="row-name">{{ $log->to_status->label() }}</span>
                    <span class="row-note">{{ $log->user?->name ?? __('reservation.created_by_customer') }}, {{ $log->created_at->format('H:i d/m/Y') }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
