@extends('layouts.admin')

@section('title', __('dinein.assign'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.dinein') }}</h1>
    </div>

    @include('modules.dinein.subnav')

    <p class="lead">{{ __('dinein.assign_lead') }}</p>

    @include('partials.date-nav', ['date' => $date])

    @if ($shifts->isEmpty())
        <p class="flash flash-warn">{{ __('dinein.assign_no_shifts') }}</p>
    @endif

    @php($userOptions = $users->pluck('name', 'id')->all())
    @foreach ($shifts->isEmpty() ? [null] : $shifts as $shift)
        @php($current = $existing->get((string) $shift?->id, collect()))
        <form method="post" action="{{ route('tenant.admin.dinein.assign.update') }}" class="panel assign-shift">
            @csrf
            @method('put')
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            @if ($shift)
                <input type="hidden" name="shift" value="{{ $shift->id }}">
            @endif
            <div class="panel-head">
                <h2>{{ $shift ? $shift->name : __('dinein.whole_day') }}</h2>
                @if ($shift)<small class="row-note">{{ $shift->timeRange() }}</small>@endif
            </div>

            @foreach (['s' => __('dinein.stations'), 'a' => __('dinein.service_areas')] as $prefix => $heading)
                <h3 class="assign-group">{{ $heading }}</h3>
                <div class="assign-grid">
                    @foreach ($targets->filter(fn ($target, $key) => str_starts_with($key, $prefix)) as $key => $target)
                        <div class="assign-target">
                            @include('partials.filter-multi', [
                                'name' => 'targets['.$key.']',
                                'label' => $prefix === 'a' ? $target->code.' · '.$target->name : $target->name,
                                'options' => $userOptions,
                                'values' => $current->get($key, []),
                                'all' => __('dinein.nobody'),
                            ])
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div><button class="btn" type="submit">{{ __('dinein.assign_save') }}</button></div>
        </form>
    @endforeach
@endsection
