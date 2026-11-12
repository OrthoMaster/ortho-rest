{{-- Mã QR của một ưu đãi: mở ngay sau khi tặng để khách quét lưu về điện thoại; Tải hình để gửi hoặc in. --}}
<dialog class="voucher-dialog" id="voucher-{{ $offer->id }}" aria-label="{{ __('dinein.voucher_title') }} {{ $offer->code }}" data-backdrop-close
        @if ((int) session('voucher') === $offer->id) data-auto-open @endif>
    <div class="stack">
        @include('partials.voucher-card', ['offer' => $offer])
        @if (! $offer->isUsable())
            <p class="voucher-state"><span class="badge {{ $offer->statusBadge() }}">{{ $offer->statusLabel() }}</span></p>
        @endif
        <div class="dialog-foot">
            <button class="btn" type="button" data-voucher-save data-filename="uu-dai-{{ $offer->code }}.png">{{ __('dinein.voucher_save_image') }}</button>
            <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
        </div>
    </div>
</dialog>
