<div class="date-nav">
    <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['date' => $date->subDay()->toDateString(), 'page' => null]) }}" aria-label="{{ __('reservation.previous_day') }}">‹</a>
    <form method="get" onchange="this.submit()">
        @include('partials.query-hidden', ['except' => ['date', 'page']])
        @include('partials.date-picker', ['name' => 'date', 'label' => __('reservation.date'), 'value' => $date->toDateString(), 'hideLabel' => true])
    </form>
    <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['date' => $date->addDay()->toDateString(), 'page' => null]) }}" aria-label="{{ __('reservation.next_day') }}">›</a>
</div>
