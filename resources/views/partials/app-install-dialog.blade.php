{{-- iPhone, iPad: Apple không cho trang tự hiện hộp cài, nên hướng dẫn thêm vào màn hình chính. --}}
<dialog id="app-install-ios" aria-labelledby="app-install-ios-title">
    <div class="stack">
        <h2 id="app-install-ios-title">{{ __('push.ios_title', ['name' => tenant()->name]) }}</h2>
        <ol class="install-steps">
            <li>
                <span class="install-step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="m8 7 4-4 4 4"/><path d="M7 10H5v11h14V10h-2"/></svg>
                </span>
                <span>{!! __('push.ios_step_share') !!}</span>
            </li>
            <li>
                <span class="install-step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                </span>
                <span>{!! __('push.ios_step_add') !!}</span>
            </li>
            <li>
                <span class="install-step-icon install-step-logo" aria-hidden="true">
                    <img src="{{ route('tenant.app-icon', ['size' => 180, 'v' => \App\Http\Controllers\AppController::iconVersion()]) }}" alt="">
                </span>
                <span>{!! __('push.ios_step_open', ['name' => e(tenant()->name)]) !!}</span>
            </li>
        </ol>
        <small class="row-note">{{ __('push.ios_note') }}</small>
        <div class="dialog-foot">
            <button class="btn" type="button" data-close-dialog>{{ __('push.ios_ok') }}</button>
        </div>
    </div>
</dialog>
