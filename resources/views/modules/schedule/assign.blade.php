@extends('layouts.admin')

@section('title', __('schedule.assign'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
        <div class="page-actions">
            <form method="post" action="{{ route('tenant.admin.schedule.week.defaults') }}"
                  data-confirm-title="{{ __('schedule.defaults_apply_title') }}" data-confirm-ok="{{ __('schedule.defaults_apply') }}"
                  data-confirm="{{ __('schedule.defaults_apply_confirm', ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m')]) }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button class="btn btn-quiet" type="submit">{{ __('schedule.defaults_apply') }}</button>
            </form>
            <form method="post" action="{{ route('tenant.admin.schedule.week.copy') }}"
                  data-confirm-title="{{ __('schedule.copy_title') }}" data-confirm-ok="{{ __('schedule.copy_week') }}"
                  data-confirm="{{ __('schedule.copy_confirm', ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m')]) }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button class="btn btn-quiet" type="submit">{{ __('schedule.copy_week') }}</button>
            </form>
        </div>
    </div>

    @include('modules.schedule.subnav')

    <div class="week-bar">
        @include('modules.schedule.week-nav')
        @include('modules.schedule.week-status')
    </div>

    @if ($targets->isEmpty())
        <p class="empty">{{ __('schedule.no_targets') }}</p>
    @else
        @php($byId = [])
        @foreach ($grid as $dateKey => $bySlot)
            @foreach ($bySlot as $slotKey => $byLane)
                @foreach ($byLane as $laneKey => $rows)
                    @php($byId[$dateKey][$slotKey][$laneKey] = collect($rows)->filter(fn ($row) => $users->has($row->user_id))->map(fn ($row) => ['user' => $users[$row->user_id], 'id' => $row->id])->values()->all())
                @endforeach
            @endforeach
        @endforeach
        <div class="planner is-editable" data-planner data-mode="assign"
             data-store-url="{{ route('tenant.admin.schedule.assignments.store') }}"
             data-item-url="{{ route('tenant.admin.schedule.assignments.update', 0) }}" data-csrf="{{ csrf_token() }}"
             data-delete-label="{{ __('schedule.delete_from_cell') }}" data-close-label="{{ __('ui.common.close') }}"
             data-picked-label="{{ __('schedule.picked') }}" data-pending-label="{{ __('schedule.leave_pending', ['name' => ':name', 'date' => ':date']) }}" data-error-label="{{ __('schedule.save_failed') }}">
            <script type="application/json" data-planner-leaves>@json($leaveMap)</script>
            @include('modules.schedule.grid', [
                'mode' => 'assign',
                'usedLanes' => null,
                'columns' => collect($days)->map(fn ($day) => [
                    'key' => $day->toDateString(), 'date' => $day->toDateString(), 'weekday' => $day->dayOfWeekIso,
                    'head' => __('ui.calendar.weekdays_short')[$day->dayOfWeekIso - 1], 'sub' => $day->format('d/m'),
                    'today' => $day->isToday(), 'slots' => $slots[$day->toDateString()],
                ])->all(),
                'pills' => fn ($column, $slot, $lane) => $byId[$column['key']][$slot['key']][$lane['key']] ?? [],
                'need' => fn ($column, $slot, $lane) => $needed[$column['weekday']][$slot['key']][$lane['key']] ?? null,
            ])
            @include('modules.schedule.people')
            <div class="planner-toasts" data-planner-toasts aria-live="polite"></div>
        </div>
        <p class="flash flash-warn schedule-desktop-only">{{ __('schedule.desktop_only') }}</p>
    @endif
@endsection
