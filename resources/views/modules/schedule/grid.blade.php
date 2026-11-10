{{--
    Lịch dạng bảng: hàng là khu (Khu phục vụ, Khu bàn) và việc của khu, nhóm Việc chung;
    cột là ngày (hoặc thứ, ở lịch mặc định); mỗi ô chia theo ca của ngày đó.

    $mode: view (chỉ xem) | assign (phân công) | defaults (lịch mặc định)
    $columns: list<array{key: string, head: string, sub: ?string, today: bool, slots: list<array>, date: ?string, weekday: ?int}>
    $pills(column, slot, lane): list<array{user: User, id: ?int}>
    $need(column, slot, lane): ?int số người cần (lịch mặc định)
    $usedLanes: list<string>|null chỉ hiện các hàng này (khi đang lọc)
--}}
@php($editable = $mode !== 'view')
<div class="schedule-wrap planner-board">
    <table class="schedule">
        <thead>
            <tr>
                <th scope="col" class="schedule-target">{{ __('schedule.target') }}</th>
                @foreach ($columns as $column)
                    <th scope="col" @class(['is-today' => $column['today']])>
                        {{ $column['head'] }}
                        @if ($column['sub'])<span>{{ $column['sub'] }}</span>@endif
                    </th>
                @endforeach
            </tr>
        </thead>
        @foreach (['station' => __('schedule.stations'), 'area' => __('schedule.areas'), 'general' => __('schedule.general_tasks')] as $kind => $heading)
            @php($group = $targets->where('kind', $kind))
            @php($visible = $group->filter(fn ($target) => $usedLanes === null || collect($target['lanes'])->pluck('key')->intersect($usedLanes)->isNotEmpty()))
            @continue($visible->isEmpty())
            <tbody>
                <tr class="schedule-group"><th scope="rowgroup" colspan="{{ count($columns) + 1 }}">{{ $heading }}</th></tr>
                @foreach ($visible as $target)
                    @foreach ($target['lanes'] as $index => $lane)
                        @continue($usedLanes !== null && ! in_array($lane['key'], $usedLanes, true) && ! ($index === 0 && $lane['task_id'] === null))
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
                            @foreach ($columns as $column)
                                <td @class(['is-today' => $column['today']])>
                                    <div class="cell-slots">
                                        @foreach ($column['slots'] as $slot)
                                            @php($people = $pills($column, $slot, $lane))
                                            @php($needed = $need($column, $slot, $lane))
                                            <div @class(['slot-zone', 'is-short' => $mode === 'assign' && $needed && count($people) < $needed])
                                                 @if ($editable) data-drop data-slot="{{ $slot['key'] }}" data-lane="{{ $lane['key'] }}" @endif
                                                 @if ($mode === 'assign') data-date="{{ $column['date'] }}" data-needed="{{ $needed ?? 0 }}" @endif
                                                 @if ($mode === 'defaults') data-weekday="{{ $column['weekday'] }}" @endif
                                                 @if (count($column['slots']) > 1) title="{{ $slot['name'] }} {{ $slot['time'] }}" @endif>
                                                @if (count($column['slots']) > 1 || $mode !== 'view')
                                                    <span class="slot-head">
                                                        @if (count($column['slots']) > 1)<small class="slot-label">{{ $slot['name'] }}</small>@endif
                                                        @if ($mode === 'assign' && $needed)
                                                            <small class="slot-need" data-need-badge>{{ count($people) }}/{{ $needed }}</small>
                                                        @endif
                                                        @if ($mode === 'defaults')
                                                            <label class="need-input" title="{{ __('schedule.needed_hint') }}">
                                                                <span class="visually-hidden">{{ __('schedule.needed') }}</span>
                                                                <input type="number" min="0" max="50" inputmode="numeric" value="{{ $needed }}" placeholder="{{ count($people) ?: '–' }}" data-needed-input>
                                                            </label>
                                                        @endif
                                                    </span>
                                                @endif
                                                @foreach ($people as $person)
                                                    <span class="person-pill tone-{{ $person['user']->department?->tone ?? 0 }}" title="{{ $person['user']->name }}"
                                                          @if ($editable) draggable="true" data-user="{{ $person['user']->id }}" @endif
                                                          @if ($mode === 'assign') data-assignment="{{ $person['id'] }}" @endif>{{ short_name($person['user']->name) }}</span>
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
