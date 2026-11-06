{{-- Nhận đặt bàn lúc hết chỗ: hỏi lại, đồng ý thì gửi lại kèm overbook. --}}
@if ($prompt = session('overbook_prompt'))
    <dialog class="confirm-dialog" data-auto-open aria-labelledby="overbook-prompt-title">
        <form method="post" action="{{ $prompt['url'] }}" class="stack">
            @csrf
            <input type="hidden" name="status" value="{{ $prompt['status'] }}">
            <input type="hidden" name="overbook" value="1">
            <h2 id="overbook-prompt-title">{{ __('reservation.overbook_title') }}</h2>
            <p>{{ $prompt['message'] }}</p>
            <p>{{ __('reservation.overbook_question') }}</p>
            <div class="dialog-foot">
                <button class="btn" type="submit">{{ __('reservation.overbook_confirm') }}</button>
                <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
            </div>
        </form>
    </dialog>
@endif
