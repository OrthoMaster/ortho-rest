@php($id = 'f-'.$name)
<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required ?? true) required @endif>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)<small class="error">{{ $message }}</small>@enderror
</div>
