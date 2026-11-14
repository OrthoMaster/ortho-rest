<?php

namespace App\Modules\Website;

use App\Modules\ModuleManager;
use App\Modules\Reservation\ReservationBooking;
use App\Modules\Website\Models\BookingPage;
use App\Tenancy\RestaurantLogo;
use App\Tenancy\RestaurantTheme;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;

/**
 * Trang đặt bàn quán tự tạo bằng AI: prompt cho AI, kiểm tra file tải lên, và trả
 * trang ra cho khách. Trang chạy cô lập (sandbox): không đọc được cookie của
 * Liresto, chỉ gửi được dữ liệu về Liresto. Phần chọn số người, ngày, khu, giờ
 * và việc gửi đặt bàn do script của Liresto làm (booking-widget.js), trang của
 * quán chỉ lo giao diện.
 */
final class BookingPages
{
    public const MAX_BYTES = 1_000_000;

    /** Chữ giữ chỗ trong trang, thay bằng thông tin thật khi trả trang. */
    public const PLACEHOLDERS = ['ten_quan', 'logo', 'mau_chinh', 'mau_phu', 'so_dien_thoai', 'email', 'dia_chi', 'gio_mo_cua', 'link_trang_chu'];

    public static function mode(): string
    {
        return app(ModuleManager::class)->settings(tenant(), 'website')['booking_page'] ?? 'default';
    }

    /** Trang đang chạy: trang tự tạo đã đăng (khi chọn chế độ tự tạo), không thì null (dùng trang mẫu). */
    public static function live(): ?BookingPage
    {
        return self::mode() === 'custom' ? BookingPage::published() : null;
    }

    /**
     * Kiểm tra file HTML: đủ các phần bắt buộc, không có thứ bị cấm.
     *
     * @return list<array{level: string, text: string}>
     */
    public static function check(string $html): array
    {
        $checks = [];
        $add = function (string $level, string $key, array $replace = []) use (&$checks) {
            $checks[] = ['level' => $level, 'text' => __("website.check.$key", $replace)];
        };

        if (strlen($html) > self::MAX_BYTES) {
            $add('error', 'too_big', ['size' => '1 MB']);

            return $checks;
        }

        if (! preg_match('/<html|<body|<form/i', $html)) {
            $add('error', 'not_html');

            return $checks;
        }

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $count = fn (string $query) => $xpath->query($query)->length;

        $forms = $count('//form');
        $booking = $count('//form[@data-liresto-booking]');

        if ($booking !== 1) {
            $add('error', $booking === 0 ? 'no_form' : 'many_forms');
        } else {
            $add('ok', 'form');
            $form = '//form[@data-liresto-booking]';

            foreach (['name' => true, 'phone' => true, 'email' => false, 'notes' => false] as $field => $required) {
                $found = $count("$form//*[(self::input or self::textarea) and @name='$field']") > 0;
                $add($found ? 'ok' : ($required ? 'error' : 'warn'), $found ? 'field_ok' : ($required ? 'field_missing' : 'field_optional'), ['field' => __("website.fields.$field")]);
            }

            $add($count("$form//*[@data-liresto-when]") > 0 ? 'ok' : 'error', $count("$form//*[@data-liresto-when]") > 0 ? 'when_ok' : 'when_missing');
            $add($count("$form//button[not(@type) or @type='submit'] | $form//input[@type='submit']") > 0 ? 'ok' : 'error', $count("$form//button[not(@type) or @type='submit'] | $form//input[@type='submit']") > 0 ? 'submit_ok' : 'submit_missing');

            if ($count("$form/@action") > 0) {
                $add('warn', 'action_ignored');
            }
        }

        if ($forms > $booking) {
            $add('error', 'other_forms');
        }

        // Không thu mật khẩu, thẻ ngân hàng, mã OTP trên trang đặt bàn.
        foreach ($xpath->query('//input') as $input) {
            $name = strtolower($input->getAttribute('name').' '.$input->getAttribute('id').' '.$input->getAttribute('autocomplete'));

            if (strtolower($input->getAttribute('type')) === 'password' || preg_match('/card|cvv|cvc|otp|password|mat-?khau|so-?the|cc-/', $name)) {
                $add('error', 'sensitive');
                break;
            }
        }

        if ($count('//script[@src]') > 0) {
            $add('warn', 'external_script');
        }

        if ($count('//iframe | //object | //embed') > 0) {
            $add('warn', 'frames');
        }

        if (! str_contains($html, '{{ten_quan}}')) {
            $add('warn', 'no_name');
        }

        return $checks;
    }

    /** Trang quán tự tạo, đã thay chữ giữ chỗ và gắn script đặt bàn của Liresto. */
    public static function render(BookingPage $page, bool $test = false): Response
    {
        $html = strtr($page->html, self::values());
        $config = '<script>window.LIRESTO_BOOKING = '.json_encode(self::config($test ? $page : null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG).';</script>';
        $head = '<link rel="stylesheet" href="'.versioned_asset('css/booking-widget.css').'">'
            .'<style>:root{--lb-primary:'.self::color('primary').';--lb-on-primary:'.RestaurantTheme::textOn(self::color('primary')).';}</style>';
        $foot = view('public.booking-footer', ['test' => $test])->render()
            .$config.'<script src="'.versioned_asset('js/booking-widget.js').'" defer></script>';

        $html = preg_match('#</head>#i', $html) ? preg_replace('#</head>#i', $head.'</head>', $html, 1) : $head.$html;
        $html = preg_match('#</body>#i', $html) ? preg_replace('#</body>(?!.*</body>)#is', $foot.'</body>', $html, 1) : $html.$foot;

        return response($html)->withHeaders([
            'Content-Security-Policy' => self::policy(),
            'X-Robots-Tag' => $test ? 'noindex' : 'all',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    /**
     * Trang chạy như một trang lạ: không cùng nguồn với Liresto (không đọc cookie,
     * không gọi được trang quản lý), chỉ tải script của Liresto và chỉ gửi dữ liệu về Liresto.
     */
    public static function policy(): string
    {
        $self = request()->getSchemeAndHttpHost();

        return implode('; ', [
            'sandbox allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation',
            "default-src 'none'",
            "script-src $self 'unsafe-inline'",
            "style-src $self 'unsafe-inline' https://fonts.googleapis.com",
            "font-src $self https://fonts.gstatic.com data:",
            "img-src $self https: data:",
            "connect-src $self",
            "form-action $self",
            "base-uri 'none'",
            "frame-ancestors 'self'",
        ]);
    }

    /** Cấu hình cho script đặt bàn: các địa chỉ gọi về, chế độ thử. @return array<string, mixed> */
    public static function config(?BookingPage $test = null): array
    {
        return [
            'info' => tenant_route('public.booking.info'),
            'slots' => tenant_route('public.booking.slots'),
            'submit' => tenant_route('public.booking.submit'),
            'failed' => __('website.widget.failed'),
            // Trang xem thử: gửi kèm mã thử, Liresto kiểm tra như thật nhưng không tạo đặt bàn.
            'test' => $test ? Crypt::encryptString(json_encode(['page' => $test->id, 'restaurant' => tenant()->id, 'until' => now()->addHours(2)->timestamp])) : null,
        ];
    }

    /** @return array<string, string> */
    public static function values(): array
    {
        $restaurant = tenant();
        $logo = RestaurantLogo::url($restaurant);

        return array_map(fn ($value) => e((string) $value), [
            '{{ten_quan}}' => $restaurant->name,
            '{{logo}}' => $logo ? url($logo) : route('tenant.app-icon', ['size' => 192]),
            '{{mau_chinh}}' => self::color('primary'),
            '{{mau_phu}}' => self::color('secondary'),
            '{{so_dien_thoai}}' => $restaurant->phone,
            '{{email}}' => $restaurant->email,
            '{{dia_chi}}' => $restaurant->address,
            '{{gio_mo_cua}}' => self::hoursText(),
            '{{link_trang_chu}}' => tenant_route('public.home'),
        ]);
    }

    public static function color(string $which): string
    {
        return tenant()->theme[$which] ?? ($which === 'primary' ? RestaurantTheme::PRIMARY : RestaurantTheme::SECONDARY);
    }

    /** "T3–CN: 10:00–14:00, 17:00–22:00" theo lịch tuần thường. */
    public static function hoursText(): string
    {
        $monday = CarbonImmutable::now()->startOfWeek();
        $groups = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $monday->addDays($i);
            $text = ReservationBooking::hoursText(tenant()->periodsOn($day)) ?: __('website.closed');
            $groups[$text][] = __('ui.calendar.weekdays_short')[$i];
        }

        return collect($groups)->map(fn (array $days, string $text) => (count($days) > 2 ? reset($days).'–'.end($days) : implode(', ', $days)).': '.$text)->implode(' · ');
    }

    /** Prompt chủ quán dán vào ChatGPT, Claude, Gemini… để tạo trang. */
    public static function prompt(): string
    {
        $restaurant = tenant();

        return __('website.prompt', [
            'name' => $restaurant->name,
            'primary' => self::color('primary'),
            'secondary' => self::color('secondary'),
            'phone' => $restaurant->phone ?: __('website.prompt_unknown'),
            'email' => $restaurant->email ?: __('website.prompt_unknown'),
            'address' => $restaurant->address ?: __('website.prompt_unknown'),
            'hours' => self::hoursText(),
        ]);
    }
}
