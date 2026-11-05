<div class="field" data-time-picker data-opens="{{ $opens }}" data-closes="{{ $closes }}" data-now="{{ now()->format('H:i') }}"
     data-outside="{{ __('takeaway.outside_pickup', ['opens' => $opens, 'closes' => $closes]) }}" data-past="{{ __('takeaway.pickup_past') }}">
    <span class="field-label">{{ __('takeaway.pickup_time') }}</span>
    <input type="hidden" name="pickup_time" value="{{ $value }}" data-time-value>
    <div class="time-picker">
        @foreach (['hour' => __('takeaway.hour'), 'minute' => __('takeaway.minute')] as $unit => $unitLabel)
            <div class="spin-group time-unit" data-time-unit="{{ $unit }}">
                <button type="button" data-time-step="-1" aria-label="{{ __('takeaway.decrease_time', ['unit' => $unitLabel]) }}">−</button>
                <input type="text" inputmode="numeric" maxlength="2" autocomplete="off" aria-label="{{ $unitLabel }}">
                <button type="button" data-time-step="1" aria-label="{{ __('takeaway.increase_time', ['unit' => $unitLabel]) }}">+</button>
            </div>
            @if ($unit === 'hour')<span class="time-separator" aria-hidden="true">:</span>@endif
        @endforeach
    </div>
    <small class="time-warning" data-time-warning hidden></small>
</div>
