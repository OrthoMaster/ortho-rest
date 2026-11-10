@extends('layouts.admin')

@section('title', __('schedule.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
    </div>

    @include('modules.schedule.subnav')

    @include('partials.date-nav', ['date' => $date])

    @if ($targets->isEmpty())
        <p class="empty">{{ __('schedule.no_targets') }}</p>
    @else
        @if ($slots[0]['shift'] === null)
            <p class="flash flash-warn">{{ __('schedule.no_shifts') }}</p>
        @endif
        @foreach ($conflicts as $conflict)
            <p class="flash flash-warn">{{ $conflict }}</p>
        @endforeach

        @php($options = $users->pluck('name', 'id')->all())
        @can('schedule.manage')
            <form method="post" action="{{ route('tenant.admin.schedule.day.update') }}" class="stack" data-guard data-multi-manual>
                @csrf
                @method('put')
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        @endcan

        <div class="schedule-day">
            @foreach ($slots as $slot)
                <section class="panel">
                    <div class="panel-head">
                        <h2>{{ $slot['name'] }}</h2>
                        @if ($slot['time'])<small class="row-note">{{ $slot['time'] }}</small>@endif
                    </div>
                    @foreach (['station' => __('schedule.stations'), 'area' => __('schedule.areas')] as $kind => $heading)
                        @php($group = $targets->where('kind', $kind))
                        @continue($group->isEmpty())
                        <h3 class="assign-group">{{ $heading }}</h3>
                        <div class="assign-grid">
                            @foreach ($group as $target)
                                @php($picked = $grid[$slot['key']][$target['key']] ?? [])
                                @php($label = ($target['code'] ? $target['code'].' · ' : '').$target['name'])
                                @can('schedule.manage')
                                    @include('partials.filter-multi', [
                                        'name' => 'slots['.$slot['key'].']['.$target['key'].']',
                                        'label' => $label,
                                        'options' => $options,
                                        'values' => $picked,
                                        'all' => __('schedule.nobody'),
                                    ])
                                @else
                                    <div class="assign-readonly">
                                        <span>{{ $label }}</span>
                                        <span>
                                            @forelse ($picked as $userId)
                                                @if ($users->has($userId))<span class="person-pill">{{ $users[$userId]->name }}</span>@endif
                                            @empty
                                                <small class="row-note">{{ __('schedule.empty_cell') }}</small>
                                            @endforelse
                                        </span>
                                    </div>
                                @endcan
                            @endforeach
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>

        @can('schedule.manage')
                <div><button class="btn" type="submit">{{ __('schedule.save') }}</button></div>
            </form>
        @endcan
    @endif
@endsection
