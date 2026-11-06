@extends('layouts.admin')

@section('title', __('activity.title'))

@section('content')
    <h1>{{ __('ui.restaurant.title') }}</h1>

    @include('admin.restaurant.subnav')

    <p class="lead">{{ __('activity.lead') }}</p>

    @php($keep = ['from' => $from->toDateString(), 'to' => $to->toDateString()])
    @php($areaOptions = collect(\App\Models\ActivityLog::AREAS)->mapWithKeys(fn ($key) => [$key => __('activity.areas.'.$key)])->all())
    @php($userOptions = $users->pluck('name', 'id')->all())
    @php($pills = [...filter_pills('area', $areaOptions, $areas), ...filter_pills('user', $userOptions, $userIds)])
    @component('partials.filter-bar', ['id' => 'activity', 'keep' => $keep, 'pills' => $pills, 'clearUrl' => url()->current().'?'.http_build_query($keep)])
        @slot('date')
            @include('partials.date-range-nav', ['from' => $from, 'to' => $to, 'max' => today()->toDateString(), 'maxDays' => \App\Http\Controllers\Admin\ActivityController::MAX_DAYS])
        @endslot
        @include('partials.filter-multi', ['name' => 'area', 'label' => __('activity.filter_area'), 'all' => __('activity.all_areas'), 'options' => $areaOptions, 'values' => $areas])
        @include('partials.filter-multi', ['name' => 'user', 'label' => __('activity.filter_user'), 'all' => __('activity.all_users'), 'options' => $userOptions, 'values' => $userIds])
    @endcomponent

    @if ($logs->isEmpty())
        <p class="empty">{{ __('activity.none') }}</p>
    @else
        @foreach ($logs->getCollection()->groupBy(fn ($log) => $log->created_at->toDateString()) as $day => $rows)
            <section class="day-group">
                <h2>{{ day_label(\Carbon\CarbonImmutable::parse($day)) }}</h2>
                <ul class="rows activity-list">
                    @foreach ($rows as $log)
                        <li>
                            <span class="activity-time">{{ $log->created_at->format('H:i') }}</span>
                            <div class="activity-main">
                                <strong>{{ $log->summary }}</strong>
                                @foreach ($log->changes as $change)
                                    <span class="edit-line">{{ $change }}</span>
                                @endforeach
                                <small>{{ $log->actorName() }}</small>
                            </div>
                            <span class="badge">{{ __('activity.areas.'.$log->area) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
        @include('partials.pagination', ['paginator' => $logs])
    @endif
@endsection
