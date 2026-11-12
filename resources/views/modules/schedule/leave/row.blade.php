{{-- Một đơn xin nghỉ trong danh sách. $approver: người duyệt (hiện tên, nút Duyệt). --}}
@php($pending = $leave->status === \App\Modules\Schedule\LeaveStatus::Pending)
@php($mine = $leave->user_id === auth()->id())
<li>
    <span class="row-name">
        @if ($approver)
            <span class="person-pill tone-{{ $leave->user->department?->tone ?? 0 }}">{{ $leave->user->name }}</span>
        @endif
        {{-- Một dòng: ngày · cả ngày hoặc ca · số ngày, kèm nhãn có lương / không lương khi đã duyệt. --}}
        <span class="leave-period">
            {{-- Dấu · dính vào mục trước, để khi xuống dòng không đứng đầu dòng. --}}
            <strong>{{ $leave->dateText() }}&nbsp;·</strong>
            <span>{{ $leave->partText() }}&nbsp;·</span>
            <span>{{ trans_choice('leave.days', $leave->days, ['days' => \App\Modules\Schedule\LeaveBook::format($leave->days)]) }}</span>
            @if ($leave->status === \App\Modules\Schedule\LeaveStatus::Approved)
                <span @class(['badge', 'badge-ok' => $leave->is_paid, 'badge-muted' => ! $leave->is_paid])>{{ __($leave->is_paid ? 'leave.paid' : 'leave.unpaid') }}</span>
            @endif
        </span>
        @if ($leave->reason)<small>{{ $leave->reason }}</small>@endif
        <small>
            @if ($leave->decided_at)
                {{ __('leave.decided_by', ['status' => $leave->status->label(), 'name' => $leave->decider?->name ?? '—', 'time' => $leave->decided_at->format('d/m H:i')]) }}
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
