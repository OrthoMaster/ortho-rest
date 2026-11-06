@php($id = 'f-'.$name)
<div class="field">
    <label for="{{ $id }}">{{ $label }}@if ($required ?? false)@include('partials.required')@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 3 }}" @if ($required ?? false) required @endif @isset($maxlength) maxlength="{{ $maxlength }}" @endisset @isset($placeholder) placeholder="{{ $placeholder }}" @endisset>{{ old($name, $value ?? '') }}</textarea>
    @error($name)<small class="error">{{ $message }}</small>@enderror
</div>
