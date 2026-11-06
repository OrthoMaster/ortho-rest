<div class="spin-group number-stepper" data-number-stepper>
    <button type="button" data-number-step="-1" aria-label="{{ __('ui.number.decrease', ['label' => $label]) }}">−</button>
    <span class="number-field">
        <input type="number" name="{{ $name }}" value="{{ old($name, $default) }}" min="1" max="{{ $max }}" step="1" inputmode="numeric" aria-label="{{ $label }}">
        <span>{{ $suffix }}</span>
    </span>
    <button type="button" data-number-step="1" aria-label="{{ __('ui.number.increase', ['label' => $label]) }}">+</button>
</div>
@error($name)<small class="error">{{ $message }}</small>@enderror
