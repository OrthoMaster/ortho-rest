@extends('layouts.admin')

@section('title', __('dinein.screens'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.dinein') }}</h1>
    </div>

    @include('modules.dinein.subnav')

    @if ($stations->isEmpty())
        <p class="empty">{{ __('dinein.screens_none') }}</p>
    @else
        <p class="lead">{{ __('dinein.screens_lead') }}</p>
        <ul class="table-grid station-grid">
            @foreach ($stations as $station)
                @php($count = $pending[$station->id] ?? 0)
                <li>
                    <a href="{{ route('tenant.admin.dinein.screens.show', $station) }}" @class(['table-tile', 'is-open' => $count > 0])>
                        <strong>{{ $station->name }}</strong>
                        @if ($count > 0)
                            <small class="badge badge-warn">{{ __('dinein.pending_count', ['count' => $count]) }}</small>
                        @else
                            <small>{{ __('dinein.no_pending') }}</small>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
