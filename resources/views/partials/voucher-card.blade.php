{{--
    Thẻ ưu đãi lần sau (voucher): tên quán, mức giảm, mã, hạn dùng, mã QR mở trang ưu đãi.
    Dùng ở hộp thoại sau khi tặng, trang Ưu đãi và trang khách mở bằng mã QR. Tải về thành hình được.
    $offer, $qr: hiện mã QR (mặc định có).
--}}
@php($restaurant = $offer->restaurant)
<div class="voucher-card" data-voucher-card>
    <header class="voucher-brand">
        @include('partials.restaurant-logo', ['restaurant' => $restaurant, 'size' => 'sm'])
        <strong>{{ $restaurant->name }}</strong>
    </header>
    <p class="voucher-kind">{{ __('dinein.voucher_title') }}</p>
    <strong class="voucher-value">{{ $offer->valueLabel() }}</strong>
    <p class="voucher-code">
        <small>{{ __('dinein.voucher_code') }}</small>
        <strong>{{ $offer->code }}</strong>
    </p>
    @if ($qr ?? true)
        <div class="voucher-qr" data-qr="{{ $offer->publicUrl() }}" role="img" aria-label="{{ __('dinein.voucher_scan') }}"></div>
        <small class="voucher-scan">{{ __('dinein.voucher_scan') }}</small>
    @endif
    <small class="voucher-note">
        {{ $offer->expires_on ? __('dinein.voucher_until', ['date' => $offer->expires_on->format('d/m/Y')]) : __('dinein.voucher_no_expiry') }}
        · {{ __('dinein.voucher_once') }}
    </small>
</div>
