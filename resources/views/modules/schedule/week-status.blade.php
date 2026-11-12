{{-- Trạng thái lịch tuần ở Phân công: nháp / đã công bố, nút Công bố, Tải hình, Huỷ công bố, Xoá hết lịch tuần. --}}
@php($range = ['from' => $days[0]->format('d/m'), 'to' => $days[6]->format('d/m')])
<div class="week-status">
    <span class="week-status-text">
        @if ($week->isPublished())
            <span class="badge badge-ok">{{ __('schedule.published_badge') }}</span>
            <small>{{ $week->publisher
                ? __('schedule.published_at', ['time' => $week->published_at->format('H:i'), 'date' => $week->published_at->format('d/m'), 'name' => $week->publisher->name])
                : __('schedule.published_at_nobody', ['time' => $week->published_at->format('H:i'), 'date' => $week->published_at->format('d/m')]) }}</small>
        @else
            <span class="badge badge-warn">{{ __('schedule.draft') }}</span>
            <small>{{ __('schedule.draft_hint') }}</small>
        @endif
    </span>
    <span class="week-status-actions">
        @if ($week->isPublished())
            <button class="btn btn-quiet" type="button" data-schedule-image="{{ route('tenant.admin.schedule.week.image', ['date' => $days[0]->toDateString()]) }}"
                    data-filename="{{ \Illuminate\Support\Str::slug(tenant()->name) }}-lich-{{ $days[0]->format('d-m') }}-{{ $days[6]->format('d-m-Y') }}.png"
                    data-busy-label="{{ __('schedule.downloading') }}" data-error-label="{{ __('schedule.download_failed') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/></svg>
                <span>{{ __('schedule.download_image') }}</span>
            </button>
        @else
            <form method="post" action="{{ route('tenant.admin.schedule.week.publish') }}"
                  data-confirm-title="{{ __('schedule.publish_title', $range) }}" data-confirm-ok="{{ __('schedule.publish') }}"
                  data-confirm="{{ __('schedule.publish_confirm') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $days[0]->toDateString() }}">
                <button class="btn" type="submit">{{ __('schedule.publish') }}</button>
            </form>
        @endif
        @php($canReset = $hasAssignments && $week->canReset())
        @if ($week->isPublished() || $canReset)
            @component('partials.kebab', ['label' => __('schedule.week_actions')])
                @if ($week->isPublished())
                    <form method="post" action="{{ route('tenant.admin.schedule.week.unpublish') }}"
                          data-confirm-title="{{ __('schedule.unpublish_title', $range) }}" data-confirm-ok="{{ __('schedule.unpublish') }}"
                          data-confirm="{{ __('schedule.unpublish_confirm') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $days[0]->toDateString() }}">
                        <button type="submit">{{ __('schedule.unpublish') }}</button>
                    </form>
                @endif
                @if ($canReset)
                    <form method="post" action="{{ route('tenant.admin.schedule.week.reset') }}" data-confirm-danger
                          data-confirm-title="{{ __('schedule.reset_title', $range) }}" data-confirm-ok="{{ __('schedule.reset') }}"
                          data-confirm="{{ __('schedule.reset_confirm') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $days[0]->toDateString() }}">
                        <button class="is-danger" type="submit">{{ __('schedule.reset') }}</button>
                    </form>
                @endif
            @endcomponent
        @endif
    </span>
</div>
