{{-- Khung email của quán: logo, tên, nội dung theo mẫu, chân trang liên hệ. Email không đọc được biến CSS nên màu truyền từ RestaurantMail::COLORS. --}}
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:24px 12px;background:{{ $colors['page'] }};font-family:Arial,Helvetica,sans-serif;color:{{ $colors['text'] }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:{{ $colors['card'] }};border-radius:12px;border-top:4px solid {{ $primary }};">
        <tr>
            <td style="padding:24px 28px 8px;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" width="48" height="48" style="display:inline-block;vertical-align:middle;border-radius:10px;margin-right:10px;">
                @endif
                <strong style="font-size:18px;vertical-align:middle;">{{ $restaurant->name }}</strong>
            </td>
        </tr>
        <tr>
            <td class="mail-body" style="padding:8px 28px 24px;font-size:15px;line-height:1.6;">
                <style>.mail-body a{color:{{ $primary }};}</style>
                {!! $body !!}
            </td>
        </tr>
        <tr>
            <td style="padding:16px 28px 24px;border-top:1px solid {{ $colors['line'] }};font-size:13px;line-height:1.5;color:{{ $colors['muted'] }};">
                <strong>{{ $restaurant->name }}</strong>
                @if ($restaurant->address)<br>{{ $restaurant->address }}@endif
                @if ($restaurant->phone)<br>{{ __('mail.footer_phone') }} {{ $restaurant->phone }}@endif
            </td>
        </tr>
    </table>
</body>
</html>
