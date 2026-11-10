@extends('layouts.admin')

@section('title', __('leave.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.schedule.leave.create') }}">{{ __('leave.add') }}</a>
    </div>

    @include('modules.schedule.subnav')

    {{-- Màn hình rộng: lịch nhỏ bên trái để xem còn đủ người không, danh sách đơn bên phải. --}}
    <div class="cols cols-2-xl leave-board">
    @include('modules.schedule.leave.calendar')

    <div class="stack">
        <nav class="chips" aria-label="{{ __('leave.title') }}">
            <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" @class(['chip', 'is-active' => $filter === 'pending'])>{{ __('leave.filter_pending') }}</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'all']) }}" @class(['chip', 'is-active' => $filter === 'all'])>{{ __('leave.filter_all') }}</a>
        </nav>

    @if ($leaves->isEmpty())
        <p class="empty">{{ $filter === 'pending' ? __('leave.empty_pending') : __('leave.empty') }}</p>
    @else
        <section class="panel">
            <ul class="rows leave-list">
                @foreach ($leaves as $leave)
                    @include('modules.schedule.leave.row', ['leave' => $leave, 'approver' => true])
                @endforeach
            </ul>
        </section>
    @endif
    </div>
    </div>

    @if ($approver)
        @foreach ($leaves->where('status', \App\Modules\Schedule\LeaveStatus::Pending) as $leave)
            @php($left = $balances[$leave->id])
            <dialog id="leave-dialog-{{ $leave->id }}">
                <form method="post" action="{{ route('tenant.admin.schedule.leave.decide', $leave) }}" class="stack">
                    @csrf
                    <h2>{{ __('leave.decide_title', ['name' => $leave->user->name]) }}</h2>
                    <p>
                        <strong>{{ $leave->periodText() }}</strong> · {{ trans_choice('leave.days', $leave->days, ['days' => \App\Modules\Schedule\LeaveBook::format($leave->days)]) }}
                        @if ($leave->reason)<br><small class="row-note">{{ $leave->reason }}</small>@endif
                    </p>
                    @include('modules.schedule.leave.balance', ['balance' => $left])
                    @if (($conflicts[$leave->id] ?? 0) > 0)
                        <p class="flash flash-warn">{{ __('leave.scheduled', ['name' => $leave->user->name, 'count' => $conflicts[$leave->id]]) }}</p>
                    @endif
                    <label class="toggle-row">
                        <span>{{ __('leave.pay', ['days' => \App\Modules\Schedule\LeaveBook::format($leave->days)]) }}</span>
                        <input type="hidden" name="is_paid" value="0">
                        <input type="checkbox" name="is_paid" value="1" @checked($left && $left['left'] >= $leave->days)>
                    </label>
                    @if ($left && $left['left'] < $leave->days)
                        <small class="row-note">{{ __('leave.pay_over', ['left' => \App\Modules\Schedule\LeaveBook::format($left['left'])]) }}</small>
                    @endif
                    <div class="field">
                        <label for="leave-note-{{ $leave->id }}">{{ __('leave.note') }}</label>
                        <textarea id="leave-note-{{ $leave->id }}" name="decision_note" rows="2" maxlength="300"></textarea>
                    </div>
                    <div class="dialog-foot">
                        <button class="btn" type="submit" name="action" value="approve">{{ __('leave.approve') }}</button>
                        <button class="btn btn-danger" type="submit" name="action" value="reject">{{ __('leave.reject') }}</button>
                        <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
                    </div>
                </form>
            </dialog>
        @endforeach
    @endif
@endsection
