@extends('layouts.admin')

@section('title', $type->label())

@section('content')
    <h1>{{ __('ui.restaurant.title') }}</h1>

    @include('admin.restaurant.subnav')

    <p class="lead">{{ $type->hint() }}</p>

    @foreach (__('ui.calendar.weekdays') as $index => $dayName)
        @php($day = $index + 1)
        <section class="panel">
            <div class="panel-head">
                <h2>{{ $dayName }}</h2>
                <a class="btn btn-quiet btn-sm" href="{{ route('tenant.admin.restaurant.shifts.create', ['type' => $type, 'day' => $day]) }}">{{ __('shifts.add') }}</a>
            </div>
            @if (isset($days[$day]))
                <ul class="rows">
                    @foreach ($days[$day] as $shift)
                        <li class="row-link">
                            <span class="row-name"><a href="{{ route('tenant.admin.restaurant.shifts.edit', $shift) }}">{{ $shift->name }}</a><small>{{ $shift->timeRange() }}</small></span>
                            <div class="row-tools">
                                @component('partials.kebab', ['label' => __('shifts.actions', ['name' => $shift->name])])
                                    <a href="{{ route('tenant.admin.restaurant.shifts.edit', $shift) }}">{{ __('shifts.edit') }}</a>
                                    <form method="post" action="{{ route('tenant.admin.restaurant.shifts.destroy', $shift) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('shifts.delete_confirm', ['name' => $shift->name]) }}">
                                        @csrf
                                        @method('delete')
                                        <button type="submit">{{ __('shifts.delete') }}</button>
                                    </form>
                                @endcomponent
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="row-note">{{ $type->emptyDay() }}</p>
            @endif
        </section>
    @endforeach
@endsection
