{{-- Một nhóm tuỳ chọn trong form. $key là khoá của nhóm; dùng cả làm mẫu (khoá __G__) cho JS. --}}
@php($group = $group ?? ['name' => '', 'required' => false, 'multiple' => false, 'options' => [['name' => '', 'price' => 0]]])
{{-- Máy tính: tên nhóm và các toggle cột trái, danh sách lựa chọn cột phải (rộng hơn). Điện thoại một cột. --}}
<section class="panel option-group" data-option-group data-key="{{ $key }}">
    <div class="stack option-group-side">
    <div class="option-group-head">
        <div class="field">
            <label for="group-{{ $key }}-name">{{ __('menu.option_group_name') }}@include('partials.required')</label>
            <input id="group-{{ $key }}-name" name="groups[{{ $key }}][name]" value="{{ $group['name'] }}" maxlength="60" placeholder="{{ __('menu.option_group_example') }}" required>
            @error("groups.$key.name")<small class="error">{{ $message }}</small>@enderror
        </div>
        <button class="row-btn" type="button" data-remove-group aria-label="{{ __('menu.option_group_remove') }}" title="{{ __('menu.option_group_remove') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12"/><path d="M9 7V4h6v3"/></svg>
        </button>
    </div>
    <div class="toggle-list">
        <label class="toggle-row">
            <span>{{ __('menu.option_multiple') }}<small>{{ __('menu.option_multiple_hint') }}</small></span>
            <input type="hidden" name="groups[{{ $key }}][multiple]" value="0">
            <input type="checkbox" name="groups[{{ $key }}][multiple]" value="1" @checked($group['multiple'] ?? false)>
        </label>
        <label class="toggle-row">
            <span>{{ __('menu.option_required') }}<small>{{ __('menu.option_required_hint') }}</small></span>
            <input type="hidden" name="groups[{{ $key }}][required]" value="0">
            <input type="checkbox" name="groups[{{ $key }}][required]" value="1" @checked($group['required'] ?? false)>
        </label>
    </div>
    </div>
    <div class="stack option-group-main">
    <div class="option-rows" data-option-rows>
        <div class="option-row option-row-head" aria-hidden="true">
            <span>{{ __('menu.option_name') }}</span><span>{{ __('menu.option_price') }}</span><span></span>
        </div>
        @foreach ($group['options'] as $optionKey => $option)
            @include('modules.menu.option-row', ['key' => $key, 'optionKey' => $optionKey, 'option' => $option])
        @endforeach
    </div>
    @error("groups.$key.options")<small class="error">{{ $message }}</small>@enderror
    <div>
        <button class="btn btn-quiet btn-sm" type="button" data-add-option>{{ __('menu.option_add') }}</button>
    </div>
    </div>
</section>
