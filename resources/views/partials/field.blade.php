@php($id = 'f-'.$name)
<div class="field">
    <label for="{{ $id }}">{{ $label }}@if ($required ?? true)@include('partials.required')@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type ?? 'text' }}"
           @unless (in_array($type ?? 'text', ['password', 'file'], true)) value="{{ old($name, $value ?? '') }}" @endunless
           @if ($accept ?? false) accept="{{ $accept }}" @endif
           @if ($autocomplete ?? false) autocomplete="{{ $autocomplete }}" @endif
           @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif
           @if ($required ?? true) required @endif
           @error($name, $bag ?? 'default') aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
    @isset($hint)<small>{{ $hint }}</small>@endisset
    @error($name, $bag ?? 'default')<small class="error" id="{{ $id }}-error">{{ $message }}</small>@enderror
</div>
