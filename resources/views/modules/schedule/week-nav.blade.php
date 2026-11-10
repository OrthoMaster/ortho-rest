{{-- Chuyển tuần: ‹ 9/11 – 15/11 › và nút về tuần này. --}}
@php($monday = $days[0])
<div class="week-nav">
    <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['date' => $monday->subWeek()->toDateString()]) }}" aria-label="{{ __('schedule.previous_week') }}">‹</a>
    <strong>{{ __('schedule.week_range', ['from' => $monday->format('d/m'), 'to' => $days[6]->format('d/m')]) }}</strong>
    <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['date' => $monday->addWeek()->toDateString()]) }}" aria-label="{{ __('schedule.next_week') }}">›</a>
    @unless (now()->between($monday->startOfDay(), $days[6]->endOfDay()))
        <a class="btn btn-quiet btn-sm" href="{{ request()->fullUrlWithQuery(['date' => null]) }}">{{ __('schedule.this_week') }}</a>
    @endunless
</div>
