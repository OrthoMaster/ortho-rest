{{-- Xác nhận dùng chung: form có data-confirm thì hỏi lại trước khi gửi. --}}
<dialog id="confirm-dialog" aria-labelledby="confirm-dialog-title">
    <div class="stack">
        <h2 id="confirm-dialog-title" data-confirm-title data-default="{{ __('ui.common.confirm_title') }}"></h2>
        <p data-confirm-body></p>
        <div class="dialog-foot">
            <button class="btn" type="button" data-confirm-ok data-default-label="{{ __('ui.common.confirm_ok') }}"></button>
            <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
        </div>
    </div>
</dialog>
