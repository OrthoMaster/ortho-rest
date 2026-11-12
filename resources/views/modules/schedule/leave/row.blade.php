{{-- Một đơn xin nghỉ trong danh sách. $approver: người duyệt (hiện tên, nút Duyệt). --}}
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
                @if ($leave->decided_by === null && $leave->status === \App\Modules\Schedule\LeaveStatus::Rejected)
                    {{ __('leave.auto_rejected', ['time' => $leave->decided_at->format('d/m H:i')]) }}
                @else
                    {{ __('leave.decided_by', ['status' => $leave->status->label(), 'name' => $leave->decider?->name ?? '—', 'time' => $leave->decided_at->format('d/m H:i')]) }}
                @endif
                @if ($leave->status === \App\Modules\Schedule\LeaveStatus::Approved) · {{ __($leave->is_paid ? 'leave.paid' : 'leave.unpaid') }}@endif
                @if ($leave->decision_note) · {{ $leave->decision_note }}@endif
            @else
                {{ __('leave.sent_by', ['time' => $leave->created_at->format('d/m H:i'), 'name' => $leave->creator?->name ?? '—']) }}
            @endif
        </small>
    </span>
    <span @class(['badge', $leave->status->badge()])>{{ $leave->status->label() }}</span>
    @php($canDecide = $approver && $pending)
    @php($canCancel = ($pending && $mine) || ($approver && in_array($leave->status, [\App\Modules\Schedule\LeaveStatus::Pending, \App\Modules\Schedule\LeaveStatus::Approved], true)))
    @if ($canDecide || $canCancel)
        <span class="line-tools">
        @if ($canDecide)
            <button class="btn btn-sm" type="button" data-open-dialog="leave-dialog-{{ $leave->id }}">{{ __('leave.decide') }}</button>
        @endif
        @if ($canCancel)
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
    @endif
</li>
