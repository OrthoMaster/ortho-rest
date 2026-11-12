{{-- Trang ưu đãi khách mở bằng mã QR: không cần đăng nhập. Lưu thành hình hoặc gửi cho chính mình. --}}
@extends('layouts.base')

@section('title', __('dinein.voucher_title'))

@section('body_class', 'voucher-page')

@section('body')
    <main class="voucher-main stack">
        @include('partials.voucher-card', ['offer' => $offer, 'qr' => false])
        @if ($offer->isUsable())
            <p class="voucher-hint">{{ __('dinein.voucher_page_hint') }}</p>
            <div class="voucher-actions">
                <button class="btn" type="button" data-voucher-save data-filename="uu-dai-{{ $offer->code }}.png">{{ __('dinein.voucher_save_photo') }}</button>
                <button class="btn btn-quiet" type="button" data-voucher-share hidden
                        data-share-text="{{ __('dinein.voucher_share_text', ['value' => $offer->type->label($offer->value), 'name' => $offer->restaurant->name, 'code' => $offer->code]) }}">{{ __('dinein.voucher_share') }}</button>
            </div>
        @else
            <p class="voucher-state"><span class="badge {{ $offer->statusBadge() }}">{{ $offer->used_at ? __('dinein.voucher_used_short') : __('dinein.voucher_expired') }}</span></p>
        @endif
    </main>
    @include('partials.voucher-scripts')
@endsection
