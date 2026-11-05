<div class="date-nav">
    <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['date' => $date->subDay()->toDateString()]) }}" aria-label="{{ __('reservation.previous_day') }}">‹</a>
    <form method="get">
        @foreach (request()->except('date') as $key => $queryValue)
            <input type="hidden" name="{{ $key }}" value="{{ $queryValue }}">
        @endforeach
        <input type="date" name="date" value="{{ $date->toDateString() }}" aria-label="{{ __('reservation.date') }}" onchange="this.form.submit()">
    </form>
    <a class="btn btn-quiet" href="{{ request()->fullUrlWithQuery(['date' => $date->addDay()->toDateString()]) }}" aria-label="{{ __('reservation.next_day') }}">›</a>
    @unless ($date->isToday())
        <a class="link" href="{{ request()->fullUrlWithQuery(['date' => today()->toDateString()]) }}">{{ __('reservation.today') }}</a>
    @endunless
</div>
