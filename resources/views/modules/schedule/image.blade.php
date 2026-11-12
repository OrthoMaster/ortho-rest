{{--
    Bảng phân công để tải về thành hình (PNG): logo và tên quán, tuần, ngày in.
    Trang Phân công mở trang này trong khung ẩn rồi nhận hình về; mở thẳng thì có nút Tải hình.
    Bề ngang cố định để hình giống nhau dù tải từ điện thoại hay máy tính.
--}}
@extends('layouts.base')

@section('title', __('schedule.image_title', ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m')]))

@section('body_class', 'schedule-print')

@section('body')
    @php($rows = [])
    @foreach ($grid as $dateKey => $bySlot)
        @foreach ($bySlot as $slotKey => $byLane)
            @foreach ($byLane as $laneKey => $list)
                @php($rows[$dateKey][$slotKey][$laneKey] = collect($list)->filter(fn ($row) => $users->has($row->user_id))->map(fn ($row) => ['user' => $users[$row->user_id], 'id' => $row->id])->values()->all())
            @endforeach
        @endforeach
    @endforeach

    <div class="schedule-print-bar">
        <a class="btn btn-quiet" href="{{ route('tenant.admin.schedule.assign', ['date' => $date->toDateString()]) }}">{{ __('ui.common.back') }}</a>
        <button class="btn" type="button" data-schedule-image-save data-filename="{{ \Illuminate\Support\Str::slug(tenant()->name) }}-lich-{{ $days[0]->format('d-m') }}-{{ $days[6]->format('d-m-Y') }}.png">{{ __('schedule.download_image') }}</button>
    </div>

    <div class="schedule-print-scroll">
    <div class="schedule-sheet" data-schedule-sheet>
        <header class="schedule-sheet-head">
            <span class="schedule-sheet-brand">
                @include('partials.restaurant-logo', ['restaurant' => tenant(), 'size' => 'lg'])
                <strong>{{ tenant()->name }}</strong>
            </span>
            <span class="schedule-sheet-title">
                <strong>{{ __('schedule.image_title', ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m/Y')]) }}</strong>
                <small>{{ __('schedule.printed_at', ['date' => $printedAt->format('d/m/Y'), 'time' => $printedAt->format('H:i')]) }}</small>
            </span>
        </header>

        @if ($targets->isEmpty())
            <p class="empty">{{ __('schedule.no_targets') }}</p>
        @else
            <div class="planner">
                @include('modules.schedule.grid', [
                    'mode' => 'view',
                    'usedLanes' => null,
                    'columns' => collect($days)->map(fn ($day) => [
                        'key' => $day->toDateString(), 'date' => $day->toDateString(), 'weekday' => $day->dayOfWeekIso,
                        'head' => __('ui.calendar.weekdays')[$day->dayOfWeekIso - 1], 'sub' => $day->format('d/m'),
                        'today' => false, 'slots' => $slots[$day->toDateString()],
                    ])->all(),
                    'pills' => fn ($column, $slot, $lane) => $rows[$column['key']][$slot['key']][$lane['key']] ?? [],
                    'need' => fn () => null,
                ])
            </div>
        @endif
    </div>
    </div>

    <script src="{{ versioned_asset('js/vendor/html-to-image.js') }}" defer></script>
@endsection
