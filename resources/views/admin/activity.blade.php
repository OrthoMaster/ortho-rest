@extends('layouts.admin')

@section('title', __('activity.title'))

@section('content')
    <h1>{{ __('ui.restaurant.title') }}</h1>

    @include('admin.restaurant.subnav')

    <p class="lead">{{ __('activity.lead') }}</p>

    <form method="get" class="inline-form filters">
        <select name="area" aria-label="{{ __('activity.filter_area') }}" onchange="this.form.submit()">
            <option value="">{{ __('activity.all_areas') }}</option>
            @foreach (\App\Models\ActivityLog::AREAS as $key)
                <option value="{{ $key }}" @selected($area === $key)>{{ __('activity.areas.'.$key) }}</option>
            @endforeach
        </select>
        <select name="user" aria-label="{{ __('activity.filter_user') }}" onchange="this.form.submit()">
            <option value="">{{ __('activity.all_users') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected($userId === $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-quiet" type="submit">{{ __('ui.super.search_submit') }}</button></noscript>
    </form>

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
