<div class="number-stepper" data-number-stepper>
    <button class="row-btn" type="button" data-number-step="-1" aria-label="{{ __('takeaway.decrease_value') }}">−</button>
    <span class="number-field">
        <input type="number" name="{{ $name }}" value="{{ old($name, $default) }}" min="1" max="{{ $max }}" step="1" inputmode="numeric" aria-label="{{ $label }}">
        <span>{{ $suffix }}</span>
    </span>
    <button class="row-btn" type="button" data-number-step="1" aria-label="{{ __('takeaway.increase_value') }}">+</button>
</div>
@error($name)<small class="error">{{ $message }}</small>@enderror
