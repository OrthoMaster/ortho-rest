@extends('layouts.admin')

@section('title', __('reports.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('reports.title') }}</h1>
        <a class="btn btn-quiet" href="{{ route('tenant.admin.reports.export', ['tab' => $tab, ...request()->query()]) }}">{{ __('reports.export') }}</a>
    </div>

    @include('partials.subnav', ['links' => collect($tabs)->map(fn ($key) => [
        'url' => route('tenant.admin.reports', ['tab' => $key === 'overview' ? null : $key]),
        'label' => __('reports.tabs.'.$key),
        'active' => $tab === $key,
    ])->all()])

    {{-- Bộ lọc: Doanh thu chọn ngày / tuần / tháng; các tab khác chọn khoảng ngày (tối đa 92 ngày). --}}
    <div class="report-filters">
        @if ($tab === 'revenue')
            @include('admin.reports.period', ['period' => $options['period'], 'date' => $options['date'], 'from' => $from, 'to' => $to])
        @elseif ($filters !== [])
            {{-- Nhân viên, Bán chạy: khoảng ngày và lọc chọn nhiều (người / món). --}}
            @php($keep = array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString()]))
            @php($pills = collect($filters)->flatMap(fn ($choices, $name) => filter_pills($name, $choices, $options[$name] ?? []))->all())
            @component('partials.filter-bar', ['id' => 'reports', 'keep' => $keep, 'pills' => $pills, 'clearUrl' => url()->current().'?'.http_build_query($keep)])
                @slot('date')
                    @include('partials.date-range-nav', ['from' => $from, 'to' => $to, 'max' => today()->toDateString(), 'maxDays' => \App\Http\Controllers\Admin\ReportController::MAX_DAYS])
                @endslot
                @foreach ($filters as $name => $choices)
                    @include('partials.filter-multi', ['name' => $name, 'label' => __('reports.filter_'.$name), 'all' => __('reports.all_'.$name), 'options' => $choices, 'values' => $options[$name] ?? []])
                @endforeach
            @endcomponent
        @else
            @include('partials.date-range-nav', ['from' => $from, 'to' => $to, 'max' => today()->toDateString(), 'maxDays' => \App\Http\Controllers\Admin\ReportController::MAX_DAYS])
            @if (in_array($tab, ['overview', 'service'], true))
                <form method="get" class="report-limit" onchange="this.submit()">
                    @include('partials.query-hidden', ['except' => ['limit']])
                    <label for="report-limit">{{ __('reports.limit_label') }}</label>
                    <select id="report-limit" name="limit">
                        @foreach (\App\Http\Controllers\Admin\ReportController::LIMITS as $minutes)
                            <option value="{{ $minutes }}" @selected($options['limit'] === $minutes)>{{ __('reports.limit_option', ['minutes' => $minutes]) }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        @endif
    </div>

    {{-- Khối rộng chiếm cả hàng; các khối nhỏ liền nhau xếp hai cột trên màn hình lớn. --}}
    @php($groups = [])
    @foreach ($blocks as $block)
        @if (($block['wide'] ?? false) || $groups === [] || end($groups)['wide'])
            @php($groups[] = ['wide' => (bool) ($block['wide'] ?? false), 'blocks' => [$block]])
        @else
            @php($groups[count($groups) - 1]['blocks'][] = $block)
        @endif
    @endforeach
    @foreach ($groups as $group)
        <div @class(['stack' => $group['wide'], 'cols cols-2-xl' => ! $group['wide']])>
            @foreach ($group['blocks'] as $block)
                @include('admin.reports.'.$block['type'], ['block' => $block])
            @endforeach
        </div>
    @endforeach
@endsection
