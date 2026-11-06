@extends('layouts.admin')

@section('title', __('special_days.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('ui.restaurant.title') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.restaurant.special-days.create') }}">{{ __('special_days.add') }}</a>
    </div>

    @include('admin.restaurant.subnav')

    <p class="lead">{{ __('special_days.lead') }}</p>

    @foreach (['upcoming' => $upcoming, 'past' => $past] as $group => $days)
        @if ($group === 'upcoming' || $days->isNotEmpty())
            <section class="day-group">
                <h2>{{ __('special_days.'.$group) }}</h2>
                @if ($days->isEmpty())
                    <p class="empty">{{ __('special_days.none') }}</p>
                @else
                    <ul class="rows">
                        @foreach ($days as $day)
                            <li class="row-link">
                                <span class="row-name">
                                    <a href="{{ route('tenant.admin.restaurant.special-days.edit', $day) }}">{{ day_label($day->date) }}</a>
                                    <small>{{ $day->periodsText() }}@if ($day->reason) · {{ $day->reason }}@endif</small>
                                </span>
                                <span class="badge {{ $day->type->badge() }}">{{ $day->type->label() }}</span>
                                <div class="row-tools">
                                    @component('partials.kebab', ['label' => __('special_days.actions', ['date' => $day->date->format('d/m')])])
                                        <a href="{{ route('tenant.admin.restaurant.special-days.edit', $day) }}">{{ __('special_days.edit') }}</a>
                                        <form method="post" action="{{ route('tenant.admin.restaurant.special-days.destroy', $day) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('special_days.delete_confirm', ['date' => $day->date->format('d/m/Y')]) }}">
                                            @csrf
                                            @method('delete')
                                            <button class="is-danger" type="submit">{{ __('special_days.delete') }}</button>
                                        </form>
                                    @endcomponent
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    @endforeach
@endsection
