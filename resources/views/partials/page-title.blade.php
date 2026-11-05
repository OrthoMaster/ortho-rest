<div class="page-title">
    <a class="row-btn" href="{{ $back }}" aria-label="{{ __('ui.common.back') }}" title="{{ __('ui.common.back') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m11 6-6 6 6 6"/></svg>
    </a>
    <div>
        <h1>{{ $title }}</h1>
        {{ $slot ?? '' }}
    </div>
</div>
