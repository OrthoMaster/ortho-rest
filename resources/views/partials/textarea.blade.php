@php($id = 'f-'.$name)
<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 3 }}">{{ old($name, $value ?? '') }}</textarea>
    @error($name)<small class="error">{{ $message }}</small>@enderror
</div>
