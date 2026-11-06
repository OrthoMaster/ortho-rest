{{-- Một ô lọc dạng select. $options: [value => label], $all: chữ của lựa chọn "tất cả". --}}
<label class="filter-field">
    <span>{{ $label }}</span>
    <select name="{{ $name }}">
        <option value="">{{ $all }}</option>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</label>
