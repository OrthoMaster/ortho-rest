{{-- Đồng hồ theo phút, giờ của hệ thống (múi giờ quán), app.js cập nhật mỗi phút. --}}
@php($clockNow = now())
<time @class(['clock', $class ?? null]) data-clock data-now="{{ $clockNow->getTimestampMs() }}" data-tz="{{ config('app.timezone') }}"
      data-weekdays='@json(__('ui.calendar.weekdays'))' datetime="{{ $clockNow->format('Y-m-d H:i') }}" aria-label="{{ __('ui.nav.clock') }}">
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
    <span class="clock-date" data-clock-date>{{ __('ui.calendar.weekdays')[$clockNow->dayOfWeekIso - 1] }}, {{ $clockNow->format('d/m') }}</span>
    <strong data-clock-time>{{ $clockNow->format('H:i') }}</strong>
</time>
