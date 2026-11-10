@extends('layouts.admin')

@section('title', __('leave.add'))

@section('content')
    @include('partials.page-title', ['title' => __('leave.add'), 'back' => route('tenant.admin.schedule.leave.index')])

    @php($wholeDay = (bool) old('whole_day', true))
    @php($start = old('starts_on', today()->toDateString()))

    <form method="post" action="{{ route('tenant.admin.schedule.leave.store') }}" class="stack narrow" data-guard data-leave-form>
        @csrf
        <section class="panel stack">
            @if ($approver)
                @include('partials.select', ['name' => 'user_id', 'label' => __('leave.person'), 'value' => old('user_id', auth()->id()), 'options' => $users->pluck('name', 'id')->all()])
            @else
                @include('modules.schedule.leave.balance', ['balance' => $balance])
            @endif

            <label class="toggle-row">
                <span>{{ __('leave.whole_day') }}<small>{{ __('leave.whole_day_hint') }}</small></span>
                <input type="hidden" name="whole_day" value="0">
                <input type="checkbox" name="whole_day" value="1" @checked($wholeDay) data-leave-whole>
            </label>

            <div class="form-row">
                @include('partials.date-picker', ['name' => 'starts_on', 'label' => __('leave.from'), 'value' => $start, 'min' => $approver ? null : today()->toDateString(), 'required' => true])
                <div data-leave-end @if (! $wholeDay) hidden @endif>
                    @include('partials.date-picker', ['name' => 'ends_on', 'label' => __('leave.to'), 'value' => old('ends_on', $start), 'min' => $approver ? null : today()->toDateString(), 'required' => true])
                </div>
            </div>

            {{-- Nghỉ theo ca: chỉ hiện các ca của thứ đang chọn. --}}
            <fieldset class="leave-shifts" data-leave-shifts @if ($wholeDay) hidden @endif>
                <legend>{{ __('leave.shifts') }}@include('partials.required')</legend>
                <div class="toggle-list">
                    @foreach ($shifts as $weekday => $dayShifts)
                        @foreach ($dayShifts as $shift)
                            <label class="toggle-row" data-weekday="{{ $weekday }}">
                                <span>{{ $shift->name }}<small>{{ $shift->timeRange() }}</small></span>
                                <input type="checkbox" name="shifts[]" value="{{ $shift->id }}" @checked(in_array($shift->id, array_map('intval', old('shifts', [])), true))>
                            </label>
                        @endforeach
                    @endforeach
                </div>
                <small class="row-note" data-leave-no-shifts hidden>{{ __('leave.no_shifts') }}</small>
                @error('shifts')<small class="error">{{ $message }}</small>@enderror
            </fieldset>

            @include('partials.textarea', ['name' => 'reason', 'label' => __('leave.reason'), 'value' => '', 'rows' => 2, 'maxlength' => 500, 'placeholder' => __('leave.reason_example')])
        </section>

        <div><button class="btn" type="submit">{{ __('leave.send') }}</button></div>
    </form>
@endsection
