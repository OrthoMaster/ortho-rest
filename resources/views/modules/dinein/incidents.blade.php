@extends('layouts.admin')

@section('title', __('dinein.incidents'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.dinein') }}</h1>
        <a class="btn btn-quiet" href="{{ route('tenant.admin.dinein.incidents.export', request()->query()) }}">{{ __('dinein.incidents_export') }}</a>
    </div>

    @include('modules.dinein.subnav')

    <p class="lead">{{ __('dinein.incidents_lead') }}</p>

    @php($keep = ['from' => $from->toDateString(), 'to' => $to->toDateString()])
    @php($kindOptions = collect(\App\Modules\DineIn\Models\Incident::KINDS)->mapWithKeys(fn ($kind) => [$kind => __('dinein.incident_kinds.'.$kind)])->all())
    @php($pills = filter_pills('kind', $kindOptions, $kinds))
    {{-- Máy tính: bộ lọc cột trái, danh sách cột phải. --}}
    <div class="layout-side">
        <aside>
            @component('partials.filter-bar', ['id' => 'incidents', 'class' => 'is-side', 'keep' => $keep, 'pills' => $pills, 'clearUrl' => url()->current().'?'.http_build_query($keep)])
                @slot('date')
                    @include('partials.date-range-nav', ['from' => $from, 'to' => $to, 'max' => today()->toDateString(), 'maxDays' => \App\Modules\DineIn\Http\IncidentController::MAX_DAYS])
                @endslot
                @include('partials.filter-multi', ['name' => 'kind', 'label' => __('dinein.incidents_filter_kind'), 'all' => __('dinein.incidents_all_kinds'), 'options' => $kindOptions, 'values' => $kinds])
            @endcomponent
        </aside>

        <div class="stack">
            @if ($incidents->isEmpty())
                <p class="empty">{{ __('dinein.incidents_none') }}</p>
            @else
                <section class="panel">
                    <ul class="rows incident-list">
                        @foreach ($incidents as $incident)
                            <li>
                                <span class="incident-when">{{ $incident->created_at->format('H:i d/m') }}</span>
                                <span class="row-name">
                                    <span class="incident-head">
                                        <strong>{{ __('dinein.table_title', ['table' => $incident->diningTable->label]) }}</strong>
                                        <span class="badge badge-warn">{{ $incident->kindLabel() }}</span>
                                    </span>
                                    <span class="incident-note">{{ $incident->note }}</span>
                                    <small>{{ collect([$incident->creator?->name, $incident->diningTable->customer ? $incident->diningTable->customer->name.' · '.mask_phone($incident->diningTable->customer->phone) : null])->filter()->implode(' · ') }}</small>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
                @include('partials.pagination', ['paginator' => $incidents])
            @endif
        </div>
    </div>
@endsection
