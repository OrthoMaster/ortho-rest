{{--
    Lịch tuần nhỏ cho người duyệt: hàng là bộ phận (kèm số người), cột là ngày, mỗi ô chia theo ca.
    Mỗi ca: số người đã duyệt nghỉ (đỏ) và đang xin nghỉ chờ duyệt (vàng), để biết duyệt thêm còn đủ người không.
    Bấm vào cột một ngày thì danh sách đơn lọc theo ngày đó (cột được chọn tô nổi).
--}}
@php($selected = isset($filterDay) && $filterDay ? $filterDay->toDateString() : null)
<section class="panel stack leave-calendar">
    <div class="leave-calendar-head">
        <h2>{{ __('leave.calendar_title') }}</h2>
        @include('modules.schedule.week-nav')
    </div>
    @if ($groups->isEmpty())
        <p class="empty">{{ __('leave.calendar_empty') }}</p>
    @else
        <p class="leave-cal-legend">
            <span><i class="legend-dot is-approved" aria-hidden="true"></i>{{ __('leave.calendar_approved') }}</span>
            <span><i class="legend-dot is-pending" aria-hidden="true"></i>{{ __('leave.calendar_pending') }}</span>
            <span class="leave-cal-tip">{{ __('leave.calendar_tip') }}</span>
        </p>
        <div class="table-wrap">
            <table class="leave-cal">
                <thead>
                    <tr>
                        <th scope="col"><span class="visually-hidden">{{ __('staff.department') }}</span></th>
                        @foreach ($days as $day)
                            @php($dayUrl = request()->fullUrlWithQuery(['day' => $day->toDateString(), 'date' => $days[0]->toDateString()]))
                            <th scope="col" @class(['is-today' => $day->isToday(), 'is-closed' => $calendarSlots[$day->toDateString()] === [], 'is-selected' => $selected === $day->toDateString()]) data-day-url="{{ $dayUrl }}">
                                <a href="{{ $dayUrl }}" aria-label="{{ __('leave.filter_day_pick', ['day' => __('ui.calendar.weekdays')[$day->dayOfWeekIso - 1].' '.$day->format('d/m')]) }}">{{ __('ui.calendar.weekdays_short')[$day->dayOfWeekIso - 1] }}<span>{{ $day->format('d/m') }}</span></a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr>
                            <th scope="row">
                                <span class="tone-dot tone-{{ $group['tone'] }}" aria-hidden="true"></span>
                                {{ $group['name'] }}
                                <small>{{ trans_choice('staff.department_people', $group['people'], ['count' => $group['people']]) }}</small>
                            </th>
                            @foreach ($days as $day)
                                @php($daySlots = $calendarSlots[$day->toDateString()])
                                <td @class(['is-today' => $day->isToday(), 'is-closed' => $daySlots === [], 'is-selected' => $selected === $day->toDateString()]) data-day-url="{{ request()->fullUrlWithQuery(['day' => $day->toDateString(), 'date' => $days[0]->toDateString()]) }}">
                                    @if ($daySlots === [])
                                        <small class="leave-cal-closed">{{ __('schedule.closed_day') }}</small>
                                    @endif
                                    @foreach ($daySlots as $slot)
                                        @php($count = $counts[$day->toDateString()][$slot['key']][$group['id']] ?? [])
                                        @php($off = ($count['approved'] ?? 0) + ($count['pending'] ?? 0))
                                        <span class="leave-cal-slot"
                                              title="{{ $slot['name'] }}: {{ __('leave.calendar_cell', ['approved' => $count['approved'] ?? 0, 'pending' => $count['pending'] ?? 0, 'people' => $group['people']]) }}">
                                            @if (count($daySlots) > 1)<small>{{ $slot['name'] }}</small>@endif
                                            @if ($off === 0)
                                                <span class="leave-cal-none">–</span>
                                            @else
                                                @if ($count['approved'] ?? 0)<b class="leave-count is-approved">{{ $count['approved'] }}</b>@endif
                                                @if ($count['pending'] ?? 0)<b class="leave-count is-pending">{{ $count['pending'] }}</b>@endif
                                            @endif
                                        </span>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
