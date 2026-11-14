{{-- Liresto luôn gắn dòng này cuối trang đặt bàn quán tự tạo: lỡ trang lỗi khách vẫn đặt được. --}}
<p class="lb-footer">
    {{ __('website.footer.trouble') }}
    @if (tenant()->phone)
        <a href="tel:{{ preg_replace('/[^0-9+]/', '', tenant()->phone) }}">{{ __('website.footer.call', ['phone' => tenant()->phone]) }}</a> ·
    @endif
    <a href="{{ tenant_route('public.booking.default') }}">{{ __('website.footer.simple') }}</a>
</p>
