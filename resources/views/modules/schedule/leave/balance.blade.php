{{-- Phép còn lại của một người. --}}
@if ($balance)
    <p class="leave-balance">
        <span>{{ __('leave.balance') }}</span>
        <strong @class(['is-negative' => $balance['left'] < 0])>{{ __('leave.balance_value', ['days' => \App\Modules\Schedule\LeaveBook::format($balance['left'])]) }}</strong>
        <small>{{ __('leave.balance_detail', ['earned' => \App\Modules\Schedule\LeaveBook::format($balance['earned']), 'since' => $balance['since']->format('d/m/Y'), 'used' => \App\Modules\Schedule\LeaveBook::format($balance['used'])]) }}</small>
    </p>
@else
    <p class="row-note">{{ __('leave.balance_none') }}</p>
@endif
