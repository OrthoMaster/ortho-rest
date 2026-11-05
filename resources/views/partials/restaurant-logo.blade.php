@php($logoUrl = $restaurant->logoUrl())
<span class="logo logo-{{ $size ?? 'md' }}">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ __('ui.logo.alt', ['name' => $restaurant->name]) }}">
    @else
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 17a8 8 0 0 1 16 0"/><path d="M2.5 17h19"/><path d="M12 9V7"/><path d="M10 6.5h4"/><path d="M6 20.5h12"/>
        </svg>
    @endif
</span>
