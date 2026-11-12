{{-- Ngày phép còn lại của một người (chỉ con số, không kèm số tích được / đã nghỉ). --}}
@if ($balance)
    <p class="leave-balance">
        <span>{{ __('leave.balance') }}</span>
        <strong @class(['is-negative' => $balance['left'] < 0])>{{ __('leave.balance_value', ['days' => \App\Modules\Schedule\LeaveBook::format($balance['left'])]) }}</strong>
    </p>
@else
    <p class="row-note">{{ __('leave.balance_none') }}</p>
@endif
