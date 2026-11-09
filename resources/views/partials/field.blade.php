@php($id = 'f-'.$name)
<div class="field">
    <label for="{{ $id }}">{{ $label }}@if ($required ?? true)@include('partials.required')@endif</label>
    @php($password = ($type ?? 'text') === 'password')
    @if ($password)<div class="password-wrap">@endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type ?? 'text' }}"
           @unless (in_array($type ?? 'text', ['password', 'file'], true)) value="{{ old($name, $value ?? '') }}" @endunless
           @if ($accept ?? false) accept="{{ $accept }}" @endif
           @if ($autocomplete ?? false) autocomplete="{{ $autocomplete }}" @endif
           @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif
           @if ($required ?? true) required @endif
           @error($name, $bag ?? 'default') aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
    @if ($password)
        {{-- Nút ẩn hiện mật khẩu, để người dùng xem lại mình gõ đúng chưa. --}}
        <button class="password-toggle" type="button" data-password-toggle aria-controls="{{ $id }}" aria-pressed="false"
                aria-label="{{ __('ui.password.show') }}" data-label-show="{{ __('ui.password.show') }}" data-label-hide="{{ __('ui.password.hide') }}">
            <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg data-eye-closed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1"/><path d="M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7c1.9 0 3.5-.6 4.9-1.4"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
        </button>
    </div>
    @endif
    @isset($hint)<small>{{ $hint }}</small>@endisset
    @error($name, $bag ?? 'default')<small class="error" id="{{ $id }}-error">{{ $message }}</small>@enderror
</div>
