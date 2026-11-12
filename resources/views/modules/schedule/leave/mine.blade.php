@extends('layouts.admin')

@section('title', __('leave.mine'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.schedule.leave.create') }}">{{ __('leave.add') }}</a>
    </div>

    @include('modules.schedule.subnav')

    {{-- Phép còn lại, lịch sử phép và các đơn của một người (mặc định là mình; người duyệt chọn người khác). --}}
    <div class="stack w-md">
        @if ($approver && $users->isNotEmpty())
            <form method="get" class="leave-person-pick">
                @include('partials.select-search', ['name' => 'user', 'label' => __('leave.book_of'), 'value' => $person?->id, 'options' => $users->pluck('name', 'id')->all(), 'submit' => true, 'required' => false])
            </form>
        @endif

        @if ($person === null)
            <p class="empty">{{ __('leave.book_none') }}</p>
        @else
        <section class="panel leave-balance-panel">
            @include('modules.schedule.leave.balance', ['balance' => $balance])
            @if ($approver && $balance)
                <button class="btn btn-quiet btn-sm" type="button" data-open-dialog="leave-adjust">{{ __('leave.adjust') }}</button>
            @endif
        </section>

        {{-- Lịch sử phép: cộng tự động mỗi tháng, nghỉ có lương, chỉnh tay (ai chỉnh, từ bao nhiêu thành bao nhiêu, lý do). --}}
        @if ($balance)
            <section class="stack">
                <h2>{{ __('leave.history') }}</h2>
                @if ($history === [])
                    <p class="empty">{{ __('leave.history_empty') }}</p>
                @else
                    {{-- 8 dòng mới nhất; còn lại bấm Xem thêm. --}}
                    @foreach ([array_slice($history, 0, 8), array_slice($history, 8)] as $part => $entries)
                        @continue($entries === [])
                        @if ($part === 1)<details class="leave-history-more"><summary>{{ __('leave.history_more', ['count' => count($entries)]) }}</summary>@endif
                        <section class="panel">
                            <ul class="rows leave-history">
                                @foreach ($entries as $entry)
                                    <li class="is-{{ $entry['kind'] }}">
                                        <span class="row-name">
                                            <strong>{{ $entry['text'] }}</strong>
                                            <small>{{ $entry['kind'] === 'monthly' ? $entry['at']->format('d/m/Y') : $entry['at']->format('d/m/Y H:i') }}@if ($entry['note']) · {{ $entry['note'] }}@endif</small>
                                        </span>
                                        <span class="leave-history-change">
                                            <b @class(['is-plus' => $entry['days'] > 0, 'is-minus' => $entry['days'] < 0])>{{ $entry['days'] > 0 ? '+' : '' }}{{ \App\Modules\Schedule\LeaveBook::format($entry['days']) }}</b>
                                            <small>{{ __('leave.history_after', ['days' => \App\Modules\Schedule\LeaveBook::format($entry['after'])]) }}</small>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                        @if ($part === 1)</details>@endif
                    @endforeach
                @endif
            </section>
        @endif

        @php($pendingLeaves = $leaves->where('status', \App\Modules\Schedule\LeaveStatus::Pending))
        @php($pastLeaves = $leaves->where('status', '!=', \App\Modules\Schedule\LeaveStatus::Pending))

        <section class="stack">
            <h2>{{ __('leave.mine_pending') }}</h2>
            @if ($pendingLeaves->isEmpty())
                <p class="empty">{{ __('leave.mine_pending_none') }}</p>
            @else
                <section class="panel">
                    <ul class="rows leave-list">
                        @foreach ($pendingLeaves as $leave)
                            @include('modules.schedule.leave.row', ['leave' => $leave, 'approver' => false])
                        @endforeach
                    </ul>
                </section>
            @endif
        </section>

        @if ($pastLeaves->isNotEmpty())
            <section class="stack">
                <h2>{{ __('leave.mine_history') }}</h2>
                <section class="panel">
                    <ul class="rows leave-list">
                        @foreach ($pastLeaves as $leave)
                            @include('modules.schedule.leave.row', ['leave' => $leave, 'approver' => false])
                        @endforeach
                    </ul>
                </section>
            </section>
        @endif
        @endif
    </div>

    @if ($approver && $balance)
        <dialog id="leave-adjust" @if ($errors->hasBag('adjust')) data-auto-open @endif>
            <form method="post" action="{{ route('tenant.admin.schedule.leave.adjust', $person->id) }}" class="stack" data-guard>
                @csrf
                <h2>{{ __('leave.adjust_title', ['name' => $person->name]) }}</h2>
                <p class="row-note">{{ __('leave.adjust_lead') }}</p>
                @include('modules.schedule.leave.balance', ['balance' => $balance])
                @include('partials.number-stepper', ['name' => 'left', 'label' => __('leave.adjust_left'), 'value' => $balance['left'], 'min' => -365, 'max' => 365, 'step' => 0.25, 'suffix' => __('leave.days_unit'), 'required' => true])
                @error('left', 'adjust')<small class="error">{{ $message }}</small>@enderror
                @include('partials.textarea', ['name' => 'reason', 'label' => __('leave.adjust_reason'), 'rows' => 2, 'maxlength' => 300, 'required' => true])
                @error('reason', 'adjust')<small class="error">{{ $message }}</small>@enderror
                <div class="dialog-foot">
                    <button class="btn" type="submit">{{ __('leave.adjust_save') }}</button>
                    <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
