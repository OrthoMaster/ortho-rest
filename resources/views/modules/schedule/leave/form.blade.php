@extends('layouts.admin')

@section('title', __('leave.add'))

@section('content')
    @include('partials.page-title', ['title' => __('leave.add'), 'back' => route($approver ? 'tenant.admin.schedule.leave.index' : 'tenant.admin.schedule.leave.mine')])

    @php($wholeDay = (bool) old('whole_day', true))
    @php($start = old('starts_on', today()->toDateString()))

    <form method="post" action="{{ route('tenant.admin.schedule.leave.store') }}" class="stack narrow" data-guard data-leave-form>
        @csrf
        <section class="panel stack">
            {{-- Người duyệt chọn người nghỉ (có ô tìm); nhân viên chỉ xin nghỉ cho mình. --}}
            @if ($approver)
                @include('partials.select-search', ['name' => 'user_id', 'label' => __('leave.person'), 'value' => old('user_id', $users->has(auth()->id()) ? auth()->id() : null), 'placeholder' => __('leave.pick_person'), 'options' => $users->pluck('name', 'id')->all()])
            @else
                <div class="field">
                    <span class="field-label">{{ __('leave.person') }}</span>
                    <strong class="leave-person">{{ auth()->user()->name }}</strong>
                </div>
                @include('modules.schedule.leave.balance', ['balance' => $balance])
            @endif

            <label class="toggle-row">
                <span>{{ __('leave.whole_day') }}<small>{{ __('leave.whole_day_hint') }}</small></span>
                <input type="hidden" name="whole_day" value="0">
                <input type="checkbox" name="whole_day" value="1" @checked($wholeDay) data-leave-whole>
            </label>

            @include('partials.date-picker', ['name' => 'starts_on', 'label' => __('leave.from'), 'value' => $start, 'min' => $approver ? null : today()->toDateString(), 'required' => true])
            <div data-leave-end @if (! $wholeDay) hidden @endif>
                @include('partials.date-picker', ['name' => 'ends_on', 'label' => __('leave.to'), 'value' => old('ends_on', $start), 'min' => $approver ? null : today()->toDateString(), 'required' => true])
            </div>

            {{-- Nghỉ theo ca: chỉ hiện các ca của thứ đang chọn. --}}
            <fieldset @class(['leave-shifts', 'has-error' => $errors->has('shifts')]) data-leave-shifts @if ($wholeDay) hidden @endif>
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
                {{-- Báo đỏ ngay trên máy khi bấm gửi mà chưa chọn ca; lỗi từ máy chủ cũng hiện ở đây. --}}
                <small class="error" data-leave-shift-error role="alert" @unless ($errors->has('shifts')) hidden @endunless>{{ $errors->first('shifts') ?: __('leave.pick_shift') }}</small>
            </fieldset>

            @include('partials.textarea', ['name' => 'reason', 'label' => __('leave.reason'), 'value' => '', 'rows' => 2, 'maxlength' => 500, 'placeholder' => __('leave.reason_example')])
            {{-- Người duyệt có thể duyệt ngay khi nhập đơn. --}}
            @if ($approver)
                <label class="toggle-row">
                    <span>{{ __('leave.paid_on_approve') }}<small>{{ __('leave.paid_on_approve_hint') }}</small></span>
                    <input type="hidden" name="is_paid" value="0">
                    <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid', true))>
                </label>
            @endif
        </section>

        <div class="form-actions">
            @if ($approver)
                <button class="btn" type="submit" name="action" value="approve">{{ __('leave.approve') }}</button>
                <button class="btn btn-quiet" type="submit" name="action" value="send">{{ __('leave.send') }}</button>
            @else
                <button class="btn" type="submit">{{ __('leave.send') }}</button>
            @endif
        </div>
    </form>
@endsection
