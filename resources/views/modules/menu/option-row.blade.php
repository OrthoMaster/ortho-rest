<div class="option-row" data-option-row>
    <span>
        <input name="groups[{{ $key }}][options][{{ $optionKey }}][name]" value="{{ $option['name'] }}" maxlength="60" placeholder="{{ __('menu.option_example') }}" aria-label="{{ __('menu.option_name') }}" required>
        @error("groups.$key.options.$optionKey.name")<small class="error">{{ $message }}</small>@enderror
    </span>
    <span class="number-field option-price">
        <input type="number" name="groups[{{ $key }}][options][{{ $optionKey }}][price]" value="{{ $option['price'] ?? 0 }}" min="0" max="10000000" step="1000" inputmode="numeric" aria-label="{{ __('menu.option_price') }}">
        <span>₫</span>
    </span>
    <button class="row-btn" type="button" data-remove-option aria-label="{{ __('menu.option_remove') }}" title="{{ __('menu.option_remove') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</div>
