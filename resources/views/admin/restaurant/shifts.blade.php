@extends('layouts.admin')

@section('title', $type->label())

@section('content')
    <div class="page-head">
        <h1>{{ __('ui.restaurant.title') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.restaurant.shifts.create', $type) }}">{{ __('shifts.add') }}</a>
    </div>

    @include('admin.restaurant.subnav')

    <p class="lead">{{ $type->hint() }}</p>

    @if ($columns->isEmpty())
        <p class="empty">{{ __('shifts.none') }}</p>
    @else
        <div class="table-wrap shift-table">
            <table>
                <thead>
                    <tr>
                        <th scope="col"><span class="visually-hidden">{{ __('shifts.day') }}</span></th>
                        @foreach ($columns as $name => $shifts)
                            <th scope="col">{{ $name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (__('ui.calendar.weekdays') as $index => $dayName)
                        @php($day = $index + 1)
                        <tr>
                            <th scope="row">
                                <span class="day-full">{{ $dayName }}</span>
                                <span class="day-short" aria-hidden="true">{{ __('ui.calendar.weekdays_short')[$index] }}</span>
                                @unless ($columns->contains(fn ($shifts) => $shifts->contains('weekday', $day)))
                                    <small>{{ $type->emptyDay() }}</small>
                                @endunless
                            </th>
                            @foreach ($columns as $name => $shifts)
                                <td>
                                    @forelse ($shifts->where('weekday', $day) as $shift)
                                        @component('partials.kebab', ['label' => __('shifts.actions', ['name' => $shift->name.', '.$dayName]), 'trigger' => new \Illuminate\Support\HtmlString(
                                            '<span>'.e($shift->start_time).'</span><span class="time-dash" aria-hidden="true">–</span><span>'.e($shift->end_time).'</span>'
                                            .($shift->crossesMidnight() ? '<small>'.e(__('shifts.next_day')).'</small>' : '')
                                        )])
                                            <a href="{{ route('tenant.admin.restaurant.shifts.edit', $shift) }}">{{ __('shifts.edit') }}</a>
                                            <form method="post" action="{{ route('tenant.admin.restaurant.shifts.destroy', $shift) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('shifts.delete_confirm', ['name' => $shift->name.', '.$dayName]) }}">
                                                @csrf
                                                @method('delete')
                                                <button class="is-danger" type="submit">{{ __('shifts.delete') }}</button>
                                            </form>
                                        @endcomponent
                                    @empty
                                        <a class="cell-add" href="{{ route('tenant.admin.restaurant.shifts.create', ['type' => $type, 'day' => $day, 'name' => $name]) }}" aria-label="{{ __('shifts.add_to', ['name' => $name, 'day' => $dayName]) }}">+</a>
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
