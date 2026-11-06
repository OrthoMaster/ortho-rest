{{-- Giờ và phút là hai ô riêng, phút bước 10, đổi bằng $minuteStep. Truyền $opens, $closes, $outside, $past nếu cần cảnh báo ngoài giờ. --}}
<div class="field" data-time-picker data-minute-step="{{ $minuteStep ?? 10 }}"
     @isset($opens) data-opens="{{ $opens }}" data-closes="{{ $closes }}" data-now="{{ now()->format('H:i') }}" data-outside="{{ $outside }}" data-past="{{ $past }}" @endisset>
    <span class="field-label">{{ $label }}@if ($required ?? false)@include('partials.required')@endif</span>
    <input type="hidden" name="{{ $name }}" value="{{ old($name, $value) }}" data-time-value>
    <div class="time-picker">
        @foreach (['hour' => __('ui.time.hour'), 'minute' => __('ui.time.minute')] as $unit => $unitLabel)
            <div class="spin-group time-unit" data-time-unit="{{ $unit }}">
                <button type="button" data-time-step="-1" aria-label="{{ __('ui.time.decrease', ['unit' => $unitLabel]) }}">−</button>
                <input type="text" inputmode="numeric" maxlength="2" autocomplete="off" aria-label="{{ $label }}: {{ $unitLabel }}">
                <button type="button" data-time-step="1" aria-label="{{ __('ui.time.increase', ['unit' => $unitLabel]) }}">+</button>
            </div>
            @if ($unit === 'hour')<span class="time-separator" aria-hidden="true">:</span>@endif
        @endforeach
    </div>
    @isset($hint)<small>{{ $hint }}</small>@endisset
    @error($name)<small class="error">{{ $message }}</small>@enderror
    <small class="time-warning" data-time-warning hidden></small>
</div>
