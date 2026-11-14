<?php

namespace App\Mail;

use App\Models\MailSetting;
use App\Models\MailTemplate;
use App\Models\Restaurant;
use App\Modules\Reservation\Models\Reservation;
use App\Support\SafeHtml;
use App\Tenancy\RestaurantLogo;
use App\Tenancy\RestaurantTheme;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Email quán gửi cho khách và cho người của quán, theo mẫu quán tự sửa được
 * (Email → Mẫu email). Gửi bằng máy chủ email riêng của quán nếu đã cài, chưa
 * cài thì gửi bằng email của Liresto nhưng tên người gửi là tên quán, khách bấm
 * Trả lời thì về email của quán. Gửi sau khi trả trang; lỗi gửi chỉ ghi log.
 */
class RestaurantMail
{
    /** Mẫu email: khoá => người nhận (guest: khách, staff: người của quán). */
    public const TEMPLATES = [
        'guest_received' => 'guest',
        'guest_confirmed' => 'guest',
        'guest_rejected' => 'guest',
        'guest_cancelled' => 'guest',
        'staff_new' => 'staff',
        'staff_guest_cancelled' => 'staff',
    ];

    /** Biến chèn được vào mẫu. */
    public const VARIABLES = [
        'ten_quan', 'ten_khach', 'so_dien_thoai_khach', 'ngay', 'gio', 'so_nguoi', 'khu_vuc', 'ghi_chu',
        'ma_dat_ban', 'link_dat_ban', 'so_dien_thoai_quan', 'dia_chi_quan', 'link_quan_ly',
    ];

    /** Màu khung email (trình đọc email không hiểu biến CSS). */
    public const COLORS = ['page' => '#f3f6fa', 'card' => '#ffffff', 'text' => '#1f2d3d', 'muted' => '#5b6b7c', 'line' => '#e3e9f0'];

    /** Biến chỉ có trong email cho người của quán. */
    public const STAFF_ONLY = ['link_quan_ly'];

    /** @return list<string> */
    public static function variables(string $key): array
    {
        return self::TEMPLATES[$key] === 'staff' ? self::VARIABLES : array_values(array_diff(self::VARIABLES, self::STAFF_ONLY));
    }

    /** @return array{subject: string, body: string, custom: bool} */
    public static function template(string $key): array
    {
        $custom = MailTemplate::where('key', $key)->first();

        return $custom
            ? ['subject' => $custom->subject, 'body' => $custom->body, 'custom' => true]
            : ['subject' => __("mail.templates.$key.subject"), 'body' => __("mail.templates.$key.body"), 'custom' => false];
    }

    /** Dữ liệu một đặt bàn cho các biến. @return array<string, string> */
    public static function reservationValues(Reservation $reservation): array
    {
        $reservation->loadMissing(['customer', 'area']);
        $restaurant = tenant();
        $at = $reservation->reserved_at;

        return [
            'ten_quan' => $restaurant->name,
            'ten_khach' => (string) $reservation->customer?->name,
            'so_dien_thoai_khach' => (string) $reservation->customer?->phone,
            'ngay' => __('ui.calendar.weekdays')[$at->dayOfWeekIso - 1].' '.$at->format('d/m/Y'),
            'gio' => $at->format('H:i'),
            'so_nguoi' => (string) $reservation->party_size,
            'khu_vuc' => $reservation->area?->name ?? __('mail.any_area'),
            'ghi_chu' => (string) $reservation->notes,
            'ma_dat_ban' => $reservation->code(),
            'link_dat_ban' => $reservation->publicUrl(),
            'so_dien_thoai_quan' => (string) $restaurant->phone,
            'dia_chi_quan' => (string) $restaurant->address,
            'link_quan_ly' => tenant_route('admin.reservation.show', ['reservation' => $reservation->getKey()]),
        ];
    }

    /** Dữ liệu mẫu để xem trước và gửi thử. @return array<string, string> */
    public static function sampleValues(): array
    {
        $restaurant = tenant();
        $at = now()->addDays(2)->setTime(19, 0);

        return [
            'ten_quan' => $restaurant->name,
            'ten_khach' => 'Nguyễn Văn An',
            'so_dien_thoai_khach' => '0901234567',
            'ngay' => __('ui.calendar.weekdays')[$at->dayOfWeekIso - 1].' '.$at->format('d/m/Y'),
            'gio' => $at->format('H:i'),
            'so_nguoi' => '4',
            'khu_vuc' => __('mail.any_area'),
            'ghi_chu' => __('mail.sample_note'),
            'ma_dat_ban' => 'K7P2QM',
            'link_dat_ban' => tenant_route('public.booking'),
            'so_dien_thoai_quan' => (string) $restaurant->phone,
            'dia_chi_quan' => (string) $restaurant->address,
            'link_quan_ly' => tenant_route('admin.reservation.index'),
        ];
    }

    /**
     * Ghép mẫu với dữ liệu: biến trong tiêu đề thành chữ, trong nội dung thành chữ
     * đã thoát HTML (link thành thẻ a).
     *
     * @param  array{subject: string, body: string}  $template
     * @param  array<string, string>  $values
     * @return array{subject: string, html: string}
     */
    public static function render(array $template, array $values): array
    {
        $subject = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => $values[$m[1]] ?? '', $template['subject']);
        $body = SafeHtml::clean($template['body']);
        // Biến làm href của link quán tự đặt: chỉ thay bằng URL.
        $body = preg_replace_callback('/href="\{\{([a-z_]+)\}\}"/', fn ($m) => 'href="'.e($values[$m[1]] ?? '').'"', $body);
        $body = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($values) {
            $value = $values[$m[1]] ?? '';

            return str_starts_with($m[1], 'link_') && $value !== '' ? '<a href="'.e($value).'">'.e($value).'</a>' : nl2br(e($value));
        }, $body);

        $restaurant = tenant();
        $primary = $restaurant->theme['primary'] ?? RestaurantTheme::PRIMARY;

        return [
            'subject' => trim(preg_replace('/\s+/', ' ', $subject)),
            'html' => view('mail.restaurant', [
                'restaurant' => $restaurant,
                'logo' => ($logo = RestaurantLogo::url($restaurant)) ? url($logo) : null,
                'primary' => RestaurantTheme::readableOnWhite($primary),
                'colors' => self::COLORS,
                'body' => $body,
            ])->render(),
        ];
    }

    /** Gửi email theo mẫu về một đặt bàn: cho khách (nếu có email) hoặc cho người của quán. */
    public static function reservation(string $key, Reservation $reservation): void
    {
        $to = self::TEMPLATES[$key] === 'staff' ? self::staffEmails() : array_filter([$reservation->customer?->email]);

        if ($to === []) {
            return;
        }

        $mail = self::render(self::template($key), self::reservationValues($reservation));
        $restaurant = tenant();

        defer(fn () => self::deliver($restaurant, $to, $mail, $key));
    }

    /**
     * @param  list<string>  $to
     * @param  array{subject: string, html: string}  $mail
     */
    public static function deliver(Restaurant $restaurant, array $to, array $mail, string $key = '', bool $throw = false): void
    {
        $settings = MailSetting::forRestaurant($restaurant)->first() ?? new MailSetting;

        try {
            $sender = $settings->usesSmtp()
                ? new Address($settings->from_address, $settings->from_name ?: $restaurant->name)
                : new Address(config('mail.from.address'), $settings->from_name ?: $restaurant->name);
            $reply = $restaurant->email ?: ($settings->notify_emails[0] ?? null);

            self::mailer($settings)->to($to)->send(new TemplatedMail($mail['subject'], $mail['html'], $sender, $reply, $key));
        } catch (Throwable $exception) {
            if ($throw) {
                throw $exception;
            }

            report($exception);
        }
    }

    public static function mailer(MailSetting $settings): Mailer
    {
        if (! $settings->usesSmtp()) {
            return Mail::mailer();
        }

        return Mail::build([
            'transport' => 'smtp',
            'host' => $settings->smtp_host,
            'port' => $settings->smtp_port,
            'scheme' => $settings->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'timeout' => 15,
        ]);
    }

    /** Email người của quán nhận thông báo: quán tự nhập, chưa nhập thì email chủ quán. @return list<string> */
    public static function staffEmails(): array
    {
        $emails = MailSetting::current()->notify_emails ?? [];

        if ($emails === []) {
            $emails = tenant()->users()->where('role', 'owner')->whereNotNull('email')->pluck('email')->all();
        }

        return array_values(array_unique(array_filter($emails)));
    }
}
