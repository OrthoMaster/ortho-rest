@php($id = 'f-'.$name)
<div class="field">
    @unless ($hideLabel ?? false)<span class="field-label">{{ $label }}</span>@endunless
    <div @class(['image-upload', 'image-upload-square' => $square ?? false, 'has-image' => $url ?? false]) data-image-upload>
        <img data-preview src="{{ $url ?? '' }}" alt="" @unless ($url ?? false) hidden @endunless>
        <div class="image-upload-empty" data-empty @if ($url ?? false) hidden @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="m4 17 5-4.5 3.5 3 3-2.5L20 17"/>
            </svg>
            <strong>{{ __('ui.image.choose') }}</strong>
            <small>{{ __('ui.image.drop') }}</small>
        </div>
        <span class="image-upload-change" data-change @unless ($url ?? false) hidden @endunless>{{ __('ui.image.change') }}</span>
        <input id="{{ $id }}" type="file" name="{{ $name }}" accept="image/png,image/jpeg,image/webp" aria-label="{{ $label }}" @if ($required ?? false) required @endif>
    </div>
    <div class="image-upload-bar">
        @isset($hint)<small>{{ $hint }}</small>@endisset
        @isset($removeName)
            <input type="hidden" name="{{ $removeName }}" value="0" data-remove-flag>
            <button class="link link-danger" type="button" data-remove @unless ($url ?? false) hidden @endunless>{{ __('ui.image.remove') }}</button>
        @endisset
    </div>
    @error($name)<small class="error">{{ $message }}</small>@enderror
</div>
