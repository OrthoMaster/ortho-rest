@extends('layouts.admin')

@section('title', __('schedule.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
    </div>

    @include('modules.schedule.subnav')

    <div class="week-bar">
        @include('modules.schedule.week-nav')

        {{-- Người phân công lọc theo nhân viên; người khác bật "chỉ mình tôi". --}}
        <form method="get" class="week-filter">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            @if ($canFilter)
                @include('partials.filter-multi', ['name' => 'people', 'label' => __('schedule.pick_person'), 'options' => $users->pluck('name', 'id')->all(), 'values' => $people, 'all' => __('schedule.everyone')])
            @elseif ($seeAll)
                <label class="toggle-row week-mine">
                    <span>{{ __('schedule.only_me') }}</span>
                    <input type="hidden" name="mine" value="0">
                    <input type="checkbox" name="mine" value="1" @checked($mine) onchange="this.form.submit()">
                </label>
            @endif
        </form>
    </div>

    @if ($hidden)
        <p class="empty">{{ __('schedule.not_published') }}</p>
    @elseif ($canFilter && ! $week->isPublished())
        <p class="flash flash-warn">{{ __('schedule.draft') }}: {{ __('schedule.draft_hint') }}</p>
    @endif

    @if ($people !== [] && ! $hidden)
        <p class="week-totals">
            @foreach ($people as $personId)
                @continue(! $users->has($personId))
                <span><span class="person-pill tone-{{ $users[$personId]->department?->tone ?? 0 }}">{{ $users[$personId]->name }}</span>
                    {{ __('schedule.person_total', ['shifts' => $totals[$personId]['shifts'] ?? 0, 'hours' => \App\Modules\Schedule\Roster::hours($totals[$personId]['minutes'] ?? 0)]) }}</span>
            @endforeach
        </p>
    @endif

    @if ($hidden)
    @elseif ($targets->isEmpty())
        <p class="empty">{{ __('schedule.no_targets') }}</p>
    @elseif ($usedLanes === [])
        <p class="empty">{{ __('schedule.nothing_this_week') }}</p>
    @else
        @php($rows = [])
        @foreach ($grid as $dateKey => $bySlot)
            @foreach ($bySlot as $slotKey => $byLane)
                @foreach ($byLane as $laneKey => $list)
                    @php($rows[$dateKey][$slotKey][$laneKey] = collect($list)->filter(fn ($row) => $users->has($row->user_id))->map(fn ($row) => ['user' => $users[$row->user_id], 'id' => $row->id])->values()->all())
                @endforeach
            @endforeach
        @endforeach

        <div class="planner is-view">
            @include('modules.schedule.grid', [
                'mode' => 'view',
                'columns' => collect($days)->map(fn ($day) => [
                    'key' => $day->toDateString(), 'date' => $day->toDateString(), 'weekday' => $day->dayOfWeekIso,
                    'head' => __('ui.calendar.weekdays_short')[$day->dayOfWeekIso - 1], 'sub' => $day->format('d/m'),
                    'today' => $day->isToday(), 'slots' => $slots[$day->toDateString()],
                ])->all(),
                'pills' => fn ($column, $slot, $lane) => $rows[$column['key']][$slot['key']][$lane['key']] ?? [],
                'need' => fn () => null,
            ])
        </div>

    @endif
@endsection
