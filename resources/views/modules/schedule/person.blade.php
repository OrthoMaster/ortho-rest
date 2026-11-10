@extends('layouts.admin')

@section('title', __('schedule.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
    </div>

    @include('modules.schedule.subnav')

    <div class="person-bar">
        @can('schedule.view')
            <form method="get" onchange="this.submit()" class="person-pick">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <label class="visually-hidden" for="schedule-user">{{ __('schedule.pick_person') }}</label>
                <select id="schedule-user" name="user">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($user->id === $person->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </form>
        @else
            <strong>{{ $person->name }}</strong>
        @endcan
        @include('modules.schedule.week-nav')
    </div>

    <section class="panel">
        <ul class="rows person-week">
            @foreach ($days as $day)
                @php($entries = $schedule[$day->toDateString()])
                <li @class(['is-today' => $day->isToday(), 'is-off' => $entries === []])>
                    <span class="person-day">
                        <strong>{{ __('ui.calendar.weekdays')[$day->dayOfWeekIso - 1] }}</strong>
                        <small>{{ $day->format('d/m') }}</small>
                    </span>
                    <span class="person-entries">
                        @forelse ($entries as $entry)
                            <span class="person-entry">
                                <strong>{{ $entry['slot']['name'] }}</strong>
                                @if ($entry['slot']['time'])<small>{{ $entry['slot']['time'] }}</small>@endif
                                <span>
                                    @if ($entry['target']['code'])<span class="area-code">{{ $entry['target']['code'] }}</span>@endif
                                    {{ $entry['target']['name'] }}@if ($entry['lane']['task_id']) · {{ $entry['lane']['name'] }}@endif
                                </span>
                            </span>
                        @empty
                            <small class="row-note">{{ __('schedule.person_off') }}</small>
                        @endforelse
                    </span>
                </li>
            @endforeach
        </ul>
        <p class="row-note person-total">{{ __('schedule.person_total', ['shifts' => $total['shifts'], 'hours' => \App\Modules\Schedule\Roster::hours($total['minutes'])]) }}</p>
    </section>
@endsection
