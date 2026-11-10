@extends('layouts.admin')

@section('title', __('schedule.title'))
@section('content_class', $editable ? 'is-wide' : '')

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
        @if ($editable)
            <form method="post" action="{{ route('tenant.admin.schedule.week.copy') }}"
                  data-confirm-title="{{ __('schedule.copy_title') }}" data-confirm-ok="{{ __('schedule.copy_week') }}"
                  data-confirm="{{ __('schedule.copy_confirm', ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m')]) }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button class="btn btn-quiet" type="submit">{{ __('schedule.copy_week') }}</button>
            </form>
        @endif
    </div>

    @include('modules.schedule.subnav')

    @include('modules.schedule.week-nav')

    @if ($targets->isEmpty())
        <p class="empty">{{ __('schedule.no_targets') }}</p>
    @else
        @php($pill = fn ($user) => 'tone-'.($user->department?->tone ?? 0))
        <div @class(['planner', 'is-editable' => $editable])
             @if ($editable)
                 data-planner data-store-url="{{ route('tenant.admin.schedule.assignments.store') }}"
                 data-item-url="{{ route('tenant.admin.schedule.assignments.update', 0) }}" data-csrf="{{ csrf_token() }}"
                 data-remove-label="{{ __('schedule.remove') }}" data-close-label="{{ __('ui.common.close') }}"
                 data-picked-label="{{ __('schedule.picked') }}" data-error-label="{{ __('schedule.save_failed') }}"
             @endif>
            @if ($editable)
                <script type="application/json" data-planner-leaves>@json($leaveMap)</script>
            @endif

            {{-- Hàng: khu và việc trong khu; cột: thứ hai → chủ nhật; mỗi ô chia theo ca. --}}
            <div class="schedule-wrap planner-board">
                <table class="schedule">
                    <thead>
                        <tr>
                            <th scope="col" class="schedule-target">{{ __('schedule.target') }}</th>
                            @foreach ($days as $day)
                                <th scope="col" @class(['is-today' => $day->isToday()])>
                                    {{ __('ui.calendar.weekdays_short')[$day->dayOfWeekIso - 1] }}
                                    <span>{{ $day->format('d/m') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach (['station' => __('schedule.stations'), 'area' => __('schedule.areas'), 'general' => __('schedule.general_tasks')] as $kind => $heading)
                        @php($group = $targets->where('kind', $kind))
                        @continue($group->isEmpty())
                        <tbody>
                            @if ($heading)
                                <tr class="schedule-group"><th scope="rowgroup" colspan="8">{{ $heading }}</th></tr>
                            @endif
                            @foreach ($group as $target)
                                @foreach ($target['lanes'] as $index => $lane)
                                    <tr @class(['lane-task' => $lane['task_id'], 'lane-first' => $index === 0, 'lane-general' => $kind === 'general'])>
                                        <th scope="row" class="schedule-target">
                                            @if ($lane['task_id'])
                                                @if ($index === 0 && $kind !== 'general')
                                                    <strong class="lane-head">{{ $target['name'] }}</strong>
                                                @endif
                                                <span class="lane-name">{{ $lane['name'] }}</span>
                                            @else
                                                @if ($target['code'])<span class="area-code">{{ $target['code'] }}</span>@endif
                                                {{ $target['name'] }}
                                            @endif
                                        </th>
                                        @foreach ($days as $day)
                                            @php($daySlots = $slots[$day->toDateString()])
                                            <td @class(['is-today' => $day->isToday()])>
                                                <div class="cell-slots">
                                                    @foreach ($daySlots as $slot)
                                                        @php($rows = $grid[$day->toDateString()][$slot['key']][$lane['key']] ?? [])
                                                        <div class="slot-zone" @if ($editable) data-drop data-date="{{ $day->toDateString() }}" data-slot="{{ $slot['key'] }}" data-lane="{{ $lane['key'] }}" @endif
                                                             @if (count($daySlots) > 1) title="{{ $slot['name'] }} {{ $slot['time'] }}" @endif>
                                                            @if (count($daySlots) > 1)<small class="slot-label">{{ $slot['name'] }}</small>@endif
                                                            @foreach ($rows as $row)
                                                                @continue(! $users->has($row->user_id))
                                                                @php($person = $users[$row->user_id])
                                                                <span class="person-pill {{ $pill($person) }}" @if ($editable) draggable="true" data-assignment="{{ $row->id }}" data-user="{{ $person->id }}" @endif>
                                                                    {{ $person->name }}
                                                                    @if ($editable)
                                                                        <button class="pill-remove" type="button" data-remove aria-label="{{ __('schedule.remove') }} {{ $person->name }}">✕</button>
                                                                    @endif
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>

            @if ($editable)
                {{-- Bảng nhân viên: kéo tên vào lịch; số ca, số giờ cập nhật ngay khi thả. --}}
                <aside class="panel planner-people">
                    <div>
                        <h2>{{ __('schedule.people') }}</h2>
                        <small class="section-hint">{{ __('schedule.people_hint') }}</small>
                    </div>
                    <ul class="people-list">
                        @foreach ($users as $person)
                            <li @class(['is-over' => $stats[$person->id]['over'] ?? false]) data-person="{{ $person->id }}">
                                <span class="person-pill {{ $pill($person) }}" draggable="true" data-drag-user="{{ $person->id }}" role="button" tabindex="0">{{ $person->name }}</span>
                                <small data-stat="{{ $person->id }}">{{ $stats[$person->id]['label'] ?? '' }}</small>
                                @foreach ($leaves[$person->id] ?? [] as $leave)
                                    <small @class(['leave-note', 'is-approved' => $leave->status === \App\Modules\Schedule\LeaveStatus::Approved])>
                                        {{ $leave->status === \App\Modules\Schedule\LeaveStatus::Approved ? __('schedule.leave_off') : __('schedule.leave_asked') }}: {{ $leave->periodText() }}
                                    </small>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                </aside>
                <div class="planner-toasts" data-planner-toasts aria-live="polite"></div>
            @endif
        </div>

        {{-- Điện thoại: mỗi ngày một thẻ, liệt kê hàng đã có người. Xếp lịch làm trên máy tính. --}}
        <div class="schedule-cards">
            @if ($editable)
                <p class="flash flash-warn">{{ __('schedule.desktop_only') }}</p>
            @endif
            @foreach ($days as $day)
                @php($daySlots = $slots[$day->toDateString()])
                <section @class(['panel', 'schedule-card', 'is-today' => $day->isToday()])>
                    <span class="schedule-card-head">
                        <strong>{{ __('ui.calendar.weekdays')[$day->dayOfWeekIso - 1] }} {{ $day->format('d/m') }}</strong>
                        @if ($day->isToday())<small class="badge">{{ __('schedule.today') }}</small>@endif
                    </span>
                    @php($any = false)
                    @foreach ($targets as $target)
                        @foreach ($target['lanes'] as $lane)
                            @php($filled = collect($daySlots)->filter(fn ($slot) => ! empty($grid[$day->toDateString()][$slot['key']][$lane['key']] ?? [])))
                            @continue($filled->isEmpty())
                            @php($any = true)
                            <span class="schedule-card-row">
                                <span class="schedule-card-target">
                                    @if ($target['code'])<span class="area-code">{{ $target['code'] }}</span> @endif{{ $target['name'] }}@if ($lane['task_id']) · {{ $lane['name'] }}@endif
                                </span>
                                @foreach ($filled as $slot)
                                    <span class="schedule-slot">
                                        @if (count($daySlots) > 1)<small>{{ $slot['name'] }}</small>@endif
                                        @foreach ($grid[$day->toDateString()][$slot['key']][$lane['key']] as $row)
                                            @if ($users->has($row->user_id))<span class="person-pill {{ $pill($users[$row->user_id]) }}">{{ $users[$row->user_id]->name }}</span>@endif
                                        @endforeach
                                    </span>
                                @endforeach
                            </span>
                        @endforeach
                    @endforeach
                    @unless ($any)
                        <small class="row-note">{{ __('schedule.empty_cell') }}</small>
                    @endunless
                </section>
            @endforeach
        </div>
    @endif
@endsection
