<div class="dine-alerts" data-dine-alerts data-url="{{ route('tenant.admin.dinein.poll') }}" data-csrf="{{ csrf_token() }}"
     data-serve-label="{{ __('dinein.serve') }}" data-serve-confirm="{{ __('dinein.serve_confirm') }}"
     data-ready-label="{{ __('dinein.alert_ready') }}" data-new-label="{{ __('dinein.alert_new') }}" data-open-label="{{ __('dinein.alert_open_screen') }}" hidden>
    <button class="dine-alerts-toggle" type="button" aria-expanded="false" aria-controls="dine-alerts-panel" aria-label="{{ __('dinein.alerts') }}" data-alerts-toggle>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        <span class="dine-alerts-count" data-alerts-count></span>
    </button>
    <div class="dine-alerts-panel" id="dine-alerts-panel" data-alerts-panel hidden>
        <div class="panel-head">
            <h2>{{ __('dinein.alerts') }}</h2>
            <button class="row-btn" type="button" data-alerts-close aria-label="{{ __('dinein.alert_close') }}">✕</button>
        </div>
        <div class="stack" data-alerts-body></div>
    </div>
</div>
