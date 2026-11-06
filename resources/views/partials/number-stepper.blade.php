{{-- Ô số có nút + và − (spin-group). $step mặc định 1, $suffix là chữ sau số, ví dụ "ngày". --}}
<div class="field">
    <label for="{{ $name }}-input">{{ $label }}</label>
    <div class="spin-group number-stepper" data-number-stepper>
        <button type="button" data-number-step="-1" aria-label="{{ __('ui.number.decrease', ['label' => $label]) }}">−</button>
        <span class="number-field">
            <input id="{{ $name }}-input" type="number" name="{{ $name }}" value="{{ old($name, $value) }}" min="{{ $min ?? 0 }}" @isset($max) max="{{ $max }}" @endisset step="{{ $step ?? 1 }}" inputmode="numeric">
            @isset($suffix)<span>{{ $suffix }}</span>@endisset
        </span>
        <button type="button" data-number-step="1" aria-label="{{ __('ui.number.increase', ['label' => $label]) }}">+</button>
    </div>
    @isset($hint)<small>{{ $hint }}</small>@endisset
    @error($name)<small class="error">{{ $message }}</small>@enderror
</div>
