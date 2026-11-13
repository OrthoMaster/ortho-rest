{{-- Một dòng module: toggle bật / tắt; module con lùi vào, chỉ bật được khi module cha đang bật. --}}
@php($core = $modules->isCore($key))
@php($available = $modules->isAvailable($key))
@php($on = $core || $modules->isEnabled($restaurant, $key))
<li @class(['module-row', 'is-child' => $depth > 0])>
    <label class="module-switch">
        <span class="module-name">
            {{ __($definition['name']) }}
            @if ($core)
                <small class="row-note">{{ __('ui.super.module_always_on') }}</small>
            @elseif (! $available)
                <small class="row-note">{{ __('ui.super.module_phase2') }}</small>
            @endif
        </span>
        <input type="checkbox" name="modules[]" value="{{ $key }}" @checked($on && $available) @disabled($core || ! $available)
               data-module="{{ $key }}" data-module-label="{{ __($definition['name']) }}" @isset($parent) data-module-parent="{{ $parent }}" @endisset
               data-module-was="{{ $on && $available ? 1 : 0 }}">
    </label>
</li>
