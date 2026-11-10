@extends('layouts.admin')

@section('title', __('schedule.defaults'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
    </div>

    @include('modules.schedule.subnav')

    <p class="lead">{{ __('schedule.defaults_lead') }}</p>

    @if ($targets->isEmpty())
        <p class="empty">{{ __('schedule.no_targets') }}</p>
    @else
        <div class="planner is-editable" data-planner data-mode="defaults"
             data-store-url="{{ route('tenant.admin.schedule.defaults.update') }}" data-csrf="{{ csrf_token() }}"
             data-delete-label="{{ __('schedule.delete_from_cell') }}" data-close-label="{{ __('ui.common.close') }}"
             data-picked-label="{{ __('schedule.picked') }}" data-error-label="{{ __('schedule.save_failed') }}">
            <script type="application/json" data-planner-leaves>{}</script>
            @include('modules.schedule.grid', [
                'mode' => 'defaults',
                'usedLanes' => null,
                'columns' => collect($days)->map(fn ($day) => [
                    'key' => (string) $day->dayOfWeekIso, 'date' => null, 'weekday' => $day->dayOfWeekIso,
                    'head' => __('ui.calendar.weekdays')[$day->dayOfWeekIso - 1], 'sub' => null,
                    'today' => false, 'slots' => $slots[$day->dayOfWeekIso],
                ])->all(),
                'pills' => fn ($column, $slot, $lane) => collect(($grid[$column['weekday']][$slot['key']][$lane['key']] ?? null)?->user_ids ?? [])
                    ->filter(fn ($id) => $users->has($id))->map(fn ($id) => ['user' => $users[$id], 'id' => null])->values()->all(),
                'need' => fn ($column, $slot, $lane) => ($grid[$column['weekday']][$slot['key']][$lane['key']] ?? null)?->needed,
            ])
            @include('modules.schedule.people', ['leaves' => []])
            <div class="planner-toasts" data-planner-toasts aria-live="polite"></div>
        </div>
        <p class="flash flash-warn schedule-desktop-only">{{ __('schedule.desktop_only') }}</p>
    @endif
@endsection
