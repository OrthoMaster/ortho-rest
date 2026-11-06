{{-- Chọn khoảng ngày trên lịch: bấm ngày đầu rồi ngày cuối. $maxDays giới hạn độ dài, $max là ngày lớn nhất. --}}
<div class="field" data-date-range data-today="{{ today()->toDateString() }}" data-max="{{ $max ?? '' }}" data-max-days="{{ $maxDays }}"
     data-weekdays="{{ json_encode(array_values(__('ui.calendar.weekdays')), JSON_UNESCAPED_UNICODE) }}"
     data-month-label="{{ __('ui.calendar.month') }}" data-today-label="{{ __('ui.calendar.today') }}"
     data-start-hint="{{ __('ui.calendar.range_start') }}" data-end-hint="{{ __('ui.calendar.range_end', ['days' => $maxDays]) }}">
    <span class="field-label visually-hidden" id="range-label">{{ $label }}</span>
    <input type="hidden" name="from" value="{{ $from->toDateString() }}" data-range-from>
    <input type="hidden" name="to" value="{{ $to->toDateString() }}" data-range-to>
    <button class="picker-trigger" type="button" aria-labelledby="range-label" data-date-trigger>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/></svg>
        <span data-date-text>{{ $from->format('d/m') }} – {{ $to->format('d/m') }}</span>
    </button>

    <dialog class="calendar" data-date-dialog aria-label="{{ $label }}">
        <div class="calendar-head">
            <button class="row-btn" type="button" data-month-step="-1" aria-label="{{ __('ui.calendar.previous') }}">‹</button>
            <strong data-month-title aria-live="polite"></strong>
            <button class="row-btn" type="button" data-month-step="1" aria-label="{{ __('ui.calendar.next') }}">›</button>
        </div>
        <div class="calendar-grid calendar-weekdays" aria-hidden="true">
            @foreach (__('ui.calendar.weekdays_short') as $day)
                <span>{{ $day }}</span>
            @endforeach
        </div>
        <div class="calendar-grid" data-month-days></div>
        <p class="calendar-hint" data-range-status aria-live="polite"></p>
        <div class="calendar-foot">
            <button class="btn btn-quiet btn-sm" type="button" data-range-shortcut="0">{{ __('ui.calendar.today') }}</button>
            <button class="btn btn-quiet btn-sm" type="button" data-range-shortcut="6">{{ __('ui.calendar.last_days', ['days' => 7]) }}</button>
            <button class="btn btn-quiet btn-sm" type="button" data-range-shortcut="{{ $maxDays - 1 }}">{{ __('ui.calendar.last_days', ['days' => $maxDays]) }}</button>
            <button class="link" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
        </div>
    </dialog>
</div>
