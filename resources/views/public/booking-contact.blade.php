{{-- Chân trang đặt bàn: liên hệ của quán, mục nào có mới hiện, có icon. Điện thoại và email chung một dòng. --}}
@php($phone = $restaurant->phone ? preg_replace('/[^0-9+]/', '', $restaurant->phone) : null)
<footer class="booking-foot">
    @if ($restaurant->address)
        <p><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>{{ $restaurant->address }}</p>
    @endif
    @if ($phone || $restaurant->email)
        <p class="booking-foot-row">
            @if ($phone)
                <a href="tel:{{ $phone }}"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>{{ $restaurant->phone }}</a>
            @endif
            @if ($restaurant->email)
                <a href="mailto:{{ $restaurant->email }}"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>{{ $restaurant->email }}</a>
            @endif
        </p>
    @endif
    @if ($hours ?? null)
        <p><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ $hours }}</p>
    @endif
</footer>
