{{--
    Ô chọn một giá trị có ô tìm (gõ không dấu cũng tìm được). Dùng thay <select> khi danh sách
    có thể dài (nhân viên, khách...), để không phải cuộn cả danh sách.
    $options: [value => label], $value: giá trị đang chọn, $required (mặc định true), $hint,
    $submit: chọn xong thì gửi form luôn (bộ chọn xem theo người...).
--}}
@php($id = 'f-'.str_replace(['[', ']'], ['-', ''], $name))
@php($current = (string) old($name, $value ?? ''))
<div class="field pick" data-pick @if ($submit ?? false) data-pick-submit @endif>
    <span class="field-label" id="{{ $id }}-label">{{ $label }}@if ($required ?? true)@include('partials.required')@endif</span>
    <button class="pick-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="{{ $id }}-label {{ $id }}-value" data-pick-trigger>
        <span id="{{ $id }}-value" data-pick-value>{{ $options[$current] ?? ($placeholder ?? '') }}</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="multi-panel pick-panel" data-pick-panel hidden>
        <input class="multi-search" type="search" placeholder="{{ __('ui.filter.search') }}" aria-label="{{ __('ui.filter.search_in', ['label' => $label]) }}" autocomplete="off" data-pick-search>
        <ul class="multi-options" aria-labelledby="{{ $id }}-label">
            @foreach ($options as $optionValue => $optionLabel)
                <li data-pick-option="{{ $optionLabel }}" data-order="{{ $loop->index }}">
                    <label class="multi-option">
                        <input type="radio" name="{{ $name }}" value="{{ $optionValue }}" data-label="{{ $optionLabel }}" @checked($current === (string) $optionValue) @if (($required ?? true) && $loop->first) required @endif>
                        <span>{{ $optionLabel }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                    </label>
                </li>
            @endforeach
        </ul>
        <p class="multi-empty" data-pick-empty hidden>{{ __('ui.filter.no_match') }}</p>
    </div>
    @if (! empty($hint))<small class="hint">{{ $hint }}</small>@endif
    @error($name)<small class="error">{{ $message }}</small>@enderror
</div>
