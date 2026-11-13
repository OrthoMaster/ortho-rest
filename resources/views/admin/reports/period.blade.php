{{-- Doanh thu: Ngày / Tuần / Tháng, và ‹ kỳ › để lùi tiến. --}}
@php($step = fn (int $direction) => match ($period) {
    'day' => $date->addDays($direction),
    'month' => $date->startOfMonth()->addMonthsNoOverflow($direction),
    default => $date->addWeeks($direction),
})
@php($next = $step(1))
<div class="segmented report-period" role="group" aria-label="{{ __('reports.period_label') }}">
    @foreach (__('reports.periods') as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['period' => $key]) }}" @class(['is-active' => $period === $key]) @if ($period === $key) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
</div>
<div class="date-nav">
    <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['date' => $step(-1)->toDateString()]) }}" aria-label="{{ __('reports.previous_period') }}">‹</a>
    <strong class="report-period-title">
        @switch($period)
            @case('day') {{ __('reports.weekdays.'.$date->dayOfWeekIso) }} {{ $date->format('d/m/Y') }} @break
            @case('month') {{ __('reports.month_title', ['month' => $from->format('m/Y')]) }} @break
            @default {{ __('reports.week_title', ['from' => $from->format('d/m'), 'to' => $to->format('d/m/Y')]) }}
        @endswitch
    </strong>
    @if ($to->gte(today()))
        <span class="btn btn-quiet is-disabled" aria-hidden="true">›</span>
    @else
        <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['date' => $next->toDateString()]) }}" aria-label="{{ __('reports.next_period') }}">›</a>
    @endif
</div>
