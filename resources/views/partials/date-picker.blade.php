{{-- Ô chọn ngày hiện thứ, bấm mở lịch. $min là ngày nhỏ nhất chọn được, bỏ trống thì chọn được mọi ngày. --}}
<div class="field" data-date-picker data-min="{{ $min ?? '' }}" data-today="{{ today()->toDateString() }}"
     data-weekdays="{{ json_encode(array_values(__('ui.calendar.weekdays')), JSON_UNESCAPED_UNICODE) }}"
     data-weekdays-short="{{ json_encode(array_values(__('ui.calendar.weekdays_short')), JSON_UNESCAPED_UNICODE) }}"
     data-month-label="{{ __('ui.calendar.month') }}" data-today-label="{{ __('ui.calendar.today') }}" data-tomorrow-label="{{ __('ui.calendar.tomorrow') }}">
    <span @class(['field-label', 'visually-hidden' => $hideLabel ?? false]) id="{{ $name }}-label">{{ $label }}@if ($required ?? false)@include('partials.required')@endif</span>
    <input type="hidden" name="{{ $name }}" value="{{ old($name, $value) }}" data-date-value>
    <button class="picker-trigger" type="button" aria-labelledby="{{ $name }}-label" data-date-trigger>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/></svg>
        <span data-date-text></span>
    </button>
    @error($name)<small class="error">{{ $message }}</small>@enderror

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
        <div class="calendar-foot">
            <button class="btn btn-quiet btn-sm" type="button" data-date-shortcut="0">{{ __('ui.calendar.today') }}</button>
            <button class="btn btn-quiet btn-sm" type="button" data-date-shortcut="1">{{ __('ui.calendar.tomorrow') }}</button>
            <button class="link" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
        </div>
    </dialog>
</div>
