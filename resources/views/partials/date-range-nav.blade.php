{{-- ‹ khoảng ngày › : hai nút lùi hoặc tiến đúng bằng độ dài khoảng đang xem. --}}
@php($length = (int) $from->diffInDays($to) + 1)
@php($shift = fn (int $days) => request()->fullUrlWithQuery(['from' => $from->addDays($days)->toDateString(), 'to' => $to->addDays($days)->toDateString(), 'page' => null]))
<div class="date-nav">
    <a class="btn btn-quiet" href="{{ $shift(-$length) }}" aria-label="{{ __('ui.calendar.previous_range') }}">‹</a>
    <form method="get" onchange="this.submit()">
        @foreach (request()->except(['from', 'to', 'page']) as $key => $queryValue)
            <input type="hidden" name="{{ $key }}" value="{{ $queryValue }}">
        @endforeach
        @include('partials.date-range-picker', ['label' => __('ui.calendar.range'), 'from' => $from, 'to' => $to, 'max' => $max ?? null, 'maxDays' => $maxDays])
    </form>
    @if ($max && $to->toDateString() >= $max)
        <span class="btn btn-quiet is-disabled" aria-hidden="true">›</span>
    @else
        <a class="btn btn-quiet" href="{{ $shift($length) }}" aria-label="{{ __('ui.calendar.next_range') }}">›</a>
    @endif
</div>
