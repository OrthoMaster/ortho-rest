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
        <ul class="rows panel">
            @foreach ($stations as $station)
                <li class="row-link">
                    <span class="row-name"><a href="{{ route('tenant.admin.dinein.screens.show', $station) }}">{{ $station->name }}</a></span>
                    @if (($pending[$station->id] ?? 0) > 0)
                        <span class="badge badge-warn">{{ __('dinein.pending_count', ['count' => $pending[$station->id]]) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
