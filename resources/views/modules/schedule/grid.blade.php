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
@php($slotRows = max(1, ...array_map(fn ($column) => count($column['slots']), $columns)))
<div class="schedule-wrap planner-board">
    <table class="schedule">
        <thead>
            <tr>
                <th scope="col" class="schedule-target">{{ __('schedule.target') }}</th>
                @foreach ($columns as $column)
                    <th scope="col" @class(['is-today' => $column['today'], 'is-closed' => $column['slots'] === []])>
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
                        {{-- Mỗi ca là một dòng ngang riêng (ca thứ 1, 2... của ngày), để các ca thẳng hàng qua cả tuần. --}}
                        @for ($row = 0; $row < $slotRows; $row++)
                            <tr @class(['lane-task' => $lane['task_id'], 'lane-first' => $index === 0, 'lane-general' => $kind === 'general', 'slot-row', 'slot-tone-'.($row % 3 + 1) => $slotRows > 1, 'slot-row-next' => $row > 0])>
                                @if ($row === 0)
                                    <th scope="row" class="schedule-target" rowspan="{{ $slotRows }}">
                                        @if ($lane['task_id'])
                                            @if ($index === 0 && $kind !== 'general')
                                                <strong class="lane-head">@if ($target['code'])<span class="area-code">{{ $target['code'] }}</span>@endif{{ $target['name'] }}</strong>
                                            @endif
                                            <span class="lane-name">{{ $lane['name'] }}</span>
                                        @else
                                            {{-- Khu bàn: mã ở dòng trên, tên ở dòng dưới. --}}
                                            @if ($target['code'])<span class="area-code">{{ $target['code'] }}</span>@endif
                                            <span class="target-name">{{ $target['name'] }}</span>
                                        @endif
                                    </th>
                                @endif
                                @foreach ($columns as $column)
                                    @php($slot = $column['slots'][$row] ?? null)
                                    {{-- Ngày chưa khai ca: một ô cả ngày, kéo dài qua các dòng ca. --}}
                                    @php($wholeDay = count($column['slots']) === 1 && $column['slots'][0]['key'] === \App\Modules\Schedule\Roster::WHOLE_DAY)
                                    @continue($wholeDay && $row > 0)
                                    @if ($column['slots'] === [])
                                        {{-- Quán nghỉ ngày này: không xếp người. --}}
                                        @if ($row === 0)
                                            <td class="is-closed" rowspan="{{ $slotRows }}"><small class="slot-closed">{{ __('schedule.closed_day') }}</small></td>
                                        @endif
                                    @elseif ($slot === null)
                                        <td @class(['is-today' => $column['today'], 'is-none'])></td>
                                    @else
                                        @php($people = $pills($column, $slot, $lane))
                                        @php($needed = $need($column, $slot, $lane))
                                        {{-- Ô là cả ô bảng (không dùng height: 100%), để ô luôn cao theo số người bên trong. --}}
                                        <td @if ($wholeDay && $slotRows > 1) rowspan="{{ $slotRows }}" @endif @class(['slot-zone', 'is-today' => $column['today'], 'is-short' => $mode === 'assign' && $needed && count($people) < $needed])
                                            @if ($editable) data-drop data-slot="{{ $slot['key'] }}" data-lane="{{ $lane['key'] }}" @endif
                                            @if ($mode === 'assign') data-date="{{ $column['date'] }}" data-needed="{{ $needed ?? 0 }}" @endif
                                            @if ($mode === 'defaults') data-weekday="{{ $column['weekday'] }}" @endif
                                            title="{{ $slot['name'] }}@if ($slot['time']) {{ $slot['time'] }}@endif">
                                            <div class="slot-body">
                                                @if ($slotRows > 1 || $mode !== 'view')
                                                    <span class="slot-head">
                                                        @if ($slotRows > 1)<small class="slot-label">{{ $slot['name'] }}</small>@endif
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
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endfor
                    @endforeach
                @endforeach
            </tbody>
        @endforeach
    </table>
</div>
