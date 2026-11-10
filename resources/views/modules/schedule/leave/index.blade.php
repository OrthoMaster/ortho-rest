@extends('layouts.admin')

@section('title', __('leave.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.schedule.leave.create') }}">{{ __('leave.add') }}</a>
    </div>

    @include('modules.schedule.subnav')

    <section class="panel">
        @include('modules.schedule.leave.balance', ['balance' => $balance])
    </section>

    @if ($approver)
        <nav class="chips" aria-label="{{ __('leave.title') }}">
            <a href="{{ route('tenant.admin.schedule.leave.index') }}" @class(['chip', 'is-active' => $filter === 'pending'])>{{ __('leave.filter_pending') }}</a>
            <a href="{{ route('tenant.admin.schedule.leave.index', ['status' => 'all']) }}" @class(['chip', 'is-active' => $filter === 'all'])>{{ __('leave.filter_all') }}</a>
        </nav>
    @endif

    @if ($leaves->isEmpty())
        <p class="empty">{{ $filter === 'pending' ? __('leave.empty_pending') : __('leave.empty') }}</p>
    @else
        <section class="panel">
            <ul class="rows leave-list">
                @foreach ($leaves as $leave)
                    @php($pending = $leave->status === \App\Modules\Schedule\LeaveStatus::Pending)
                    @php($mine = $leave->user_id === auth()->id())
                    <li>
                        <span class="row-name">
                            @if ($approver)
                                <span class="person-pill tone-{{ $leave->user->department?->tone ?? 0 }}">{{ $leave->user->name }}</span>
                            @endif
                            <strong>{{ $leave->periodText() }}</strong>
                            <small>
                                {{ trans_choice('leave.days', $leave->days, ['days' => \App\Modules\Schedule\LeaveBook::format($leave->days)]) }}
                                @if ($leave->reason) · {{ $leave->reason }}@endif
                            </small>
                            <small>
                                @if ($leave->decided_at)
                                    {{ __('leave.decided_by', ['status' => $leave->status->label(), 'name' => $leave->decider?->name ?? '—', 'time' => $leave->decided_at->format('d/m H:i')]) }}
                                    @if ($leave->status === \App\Modules\Schedule\LeaveStatus::Approved) · {{ __($leave->is_paid ? 'leave.paid' : 'leave.unpaid') }}@endif
                                    @if ($leave->decision_note) · {{ $leave->decision_note }}@endif
                                @else
                                    {{ __('leave.sent_by', ['time' => $leave->created_at->format('d/m H:i'), 'name' => $leave->creator?->name ?? '—']) }}
                                @endif
                            </small>
                        </span>
                        <span @class(['badge', $leave->status->badge()])>{{ $leave->status->label() }}</span>
                        <span class="line-tools">
                            @if ($approver && $pending)
                                <button class="btn btn-sm" type="button" data-open-dialog="leave-dialog-{{ $leave->id }}">{{ __('leave.decide') }}</button>
                            @endif
                            @if (($pending && $mine) || ($approver && in_array($leave->status, [\App\Modules\Schedule\LeaveStatus::Pending, \App\Modules\Schedule\LeaveStatus::Approved], true)))
                                @component('partials.kebab', ['label' => __('leave.cancel')])
                                    <form method="post" action="{{ route('tenant.admin.schedule.leave.cancel', $leave) }}" data-confirm-danger
                                          data-confirm-title="{{ __('leave.cancel') }}?" data-confirm-ok="{{ __('leave.cancel') }}"
                                          data-confirm="{{ __('leave.cancel_confirm', ['period' => $leave->periodText()]) }}">
                                        @csrf
                                        <button class="is-danger" type="submit">{{ __('leave.cancel') }}</button>
                                    </form>
                                @endcomponent
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

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
