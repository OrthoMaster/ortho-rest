@php($id = 'f-'.$name)
<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type ?? 'text' }}"
           @if (($type ?? 'text') !== 'password') value="{{ old($name, $value ?? '') }}" @endif
           @if ($autocomplete ?? false) autocomplete="{{ $autocomplete }}" @endif
           @if ($required ?? true) required @endif
           @error($name, $bag ?? 'default') aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
    @isset($hint)<small>{{ $hint }}</small>@endisset
    @error($name, $bag ?? 'default')<small class="error" id="{{ $id }}-error">{{ $message }}</small>@enderror
</div>
