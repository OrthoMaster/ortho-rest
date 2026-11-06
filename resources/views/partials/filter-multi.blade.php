{{--
    Ô lọc chọn nhiều. Máy tính: bấm mở danh sách nổi, đóng lại thì lọc.
    Điện thoại (trong bảng lọc): bấm mở danh sách ngay bên dưới.
    Nhiều lựa chọn thì có ô tìm, gõ không dấu cũng tìm được.
    $options: [value => label], $values: các giá trị đang chọn, $all: chữ khi chưa chọn gì.
--}}
@php($values = array_map('strval', $values ?? []))
@php($picked = array_values(array_intersect_key($options, array_flip($values))))
<div class="multi" data-multi data-all="{{ $all }}">
    <button class="multi-trigger" type="button" aria-expanded="false" data-multi-trigger>
        <span class="multi-label">{{ $label }}</span>
        <span class="multi-value" data-multi-value>{{ match (count($picked)) { 0 => $all, 1 => $picked[0], default => $picked[0].' +'.(count($picked) - 1) } }}</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="multi-panel" data-multi-panel hidden>
        @if (count($options) > 6)
            <input class="multi-search" type="search" placeholder="{{ __('ui.filter.search') }}" aria-label="{{ __('ui.filter.search_in', ['label' => $label]) }}" autocomplete="off" data-multi-search>
        @endif
        <ul class="multi-options" aria-label="{{ $label }}">
            @foreach ($options as $optionValue => $optionLabel)
                <li data-multi-option="{{ $optionLabel }}">
                    <label class="multi-option">
                        <input type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" data-label="{{ $optionLabel }}" @checked(in_array((string) $optionValue, $values, true))>
                        <span>{{ $optionLabel }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                    </label>
                </li>
            @endforeach
        </ul>
        <p class="multi-empty" data-multi-empty hidden>{{ __('ui.filter.no_match') }}</p>
    </div>
</div>
