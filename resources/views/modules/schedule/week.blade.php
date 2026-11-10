@extends('layouts.admin')

@section('title', __('schedule.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
        @can('schedule.manage')
            <form method="post" action="{{ route('tenant.admin.schedule.week.copy') }}"
                  data-confirm-title="{{ __('schedule.copy_title') }}" data-confirm-ok="{{ __('schedule.copy_week') }}"
                  data-confirm="{{ __('schedule.copy_confirm', ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m')]) }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button class="btn btn-quiet" type="submit">{{ __('schedule.copy_week') }}</button>
            </form>
        @endcan
    </div>

    @include('modules.schedule.subnav')

    <p class="lead">{{ __('schedule.lead') }}</p>

    @include('modules.schedule.week-nav')

    @if ($targets->isEmpty())
        <p class="empty">{{ __('schedule.no_targets') }}</p>
    @else
        {{-- Hàng là khu, cột là ngày; bấm một ô để mở ngày đó. Điện thoại thì kéo ngang, cột khu đứng yên. --}}
        <div class="schedule-wrap">
            <table class="schedule">
                <thead>
                    <tr>
                        <th scope="col" class="schedule-target">{{ __('schedule.target') }}</th>
                        @foreach ($days as $day)
                            <th scope="col" @class(['is-today' => $day->isToday()])>
                                <a href="{{ route('tenant.admin.schedule.day', ['date' => $day->toDateString()]) }}">
                                    {{ __('ui.calendar.weekdays_short')[$day->dayOfWeekIso - 1] }}
                                    <span>{{ $day->format('d/m') }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                @foreach (['station' => __('schedule.stations'), 'area' => __('schedule.areas')] as $kind => $heading)
                    @php($group = $targets->where('kind', $kind))
                    @continue($group->isEmpty())
                    <tbody>
                        <tr class="schedule-group">
                            <th scope="rowgroup" colspan="8">{{ $heading }}</th>
                        </tr>
                        @foreach ($group as $target)
                            <tr>
                                <th scope="row" class="schedule-target">
                                    @if ($target['code'])<span class="area-code">{{ $target['code'] }}</span>@endif
                                    {{ $target['name'] }}
                                </th>
                                @foreach ($days as $day)
                                    @php($daySlots = $slots[$day->toDateString()])
                                    @php($filled = collect($daySlots)->filter(fn ($slot) => ! empty($grid[$day->toDateString()][$slot['key']][$target['key']] ?? [])))
                                    <td @class(['is-today' => $day->isToday(), 'is-empty' => $filled->isEmpty()])>
                                        <a class="schedule-cell" href="{{ route('tenant.admin.schedule.day', ['date' => $day->toDateString()]) }}"
                                           aria-label="{{ __('schedule.open_day', ['date' => $day->format('d/m')]) }} · {{ $target['name'] }}">
                                            @forelse ($filled as $slot)
                                                <span class="schedule-slot">
                                                    @if (count($daySlots) > 1)<small>{{ $slot['name'] }}</small>@endif
                                                    @foreach ($grid[$day->toDateString()][$slot['key']][$target['key']] as $userId)
                                                        @if ($users->has($userId))<span class="person-pill">{{ $users[$userId]->name }}</span>@endif
                                                    @endforeach
                                                </span>
                                            @empty
                                                <span class="schedule-none">—</span>
                                            @endforelse
                                        </a>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>

        {{-- Điện thoại: mỗi ngày một thẻ, liệt kê khu đã có người. --}}
        <div class="schedule-cards">
            @foreach ($days as $day)
                @php($daySlots = $slots[$day->toDateString()])
                <a @class(['panel', 'schedule-card', 'is-today' => $day->isToday()]) href="{{ route('tenant.admin.schedule.day', ['date' => $day->toDateString()]) }}">
                    <span class="schedule-card-head">
                        <strong>{{ __('ui.calendar.weekdays')[$day->dayOfWeekIso - 1] }} {{ $day->format('d/m') }}</strong>
                        @if ($day->isToday())<small class="badge">{{ __('schedule.today') }}</small>@endif
                    </span>
                    @php($any = false)
                    @foreach ($targets as $target)
                        @php($filled = collect($daySlots)->filter(fn ($slot) => ! empty($grid[$day->toDateString()][$slot['key']][$target['key']] ?? [])))
                        @continue($filled->isEmpty())
                        @php($any = true)
                        <span class="schedule-card-row">
                            <span class="schedule-card-target">@if ($target['code'])<span class="area-code">{{ $target['code'] }}</span> @endif{{ $target['name'] }}</span>
                            @foreach ($filled as $slot)
                                <span class="schedule-slot">
                                    @if (count($daySlots) > 1)<small>{{ $slot['name'] }}</small>@endif
                                    @foreach ($grid[$day->toDateString()][$slot['key']][$target['key']] as $userId)
                                        @if ($users->has($userId))<span class="person-pill">{{ $users[$userId]->name }}</span>@endif
                                    @endforeach
                                </span>
                            @endforeach
                        </span>
                    @endforeach
                    @unless ($any)
                        <small class="row-note">{{ __('schedule.empty_cell') }}</small>
                    @endunless
                </a>
            @endforeach
        </div>
    @endif
@endsection
