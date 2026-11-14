<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationBooking;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use App\Modules\Website\BookingPages;
use App\Modules\Website\Models\BookingPage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trang đặt bàn công khai /dat-ban: trang mẫu của Liresto (theo màu, logo quán) hoặc
 * trang quán tự tạo. Cả hai dùng chung script đặt bàn (booking-widget.js), script
 * gọi các địa chỉ JSON ở đây. Trang tự tạo chạy cô lập nên gọi như từ nơi khác:
 * không cookie, không CSRF; thay vào đó có mã trang (token) và ô bẫy bot.
 */
class BookingController extends Controller
{
    public function __construct(private ReservationBooking $booking, private ModuleManager $modules) {}

    public function page(): Response|View
    {
        $page = BookingPages::live();

        return $page ? BookingPages::render($page) : $this->defaultPage();
    }

    /** Trang mẫu: luôn có, kể cả khi quán dùng trang tự tạo (link dự phòng ở cuối trang tự tạo). */
    public function defaultPage(): View
    {
        return view('public.booking', [
            'restaurant' => tenant(),
            'config' => BookingPages::config(),
            'hours' => BookingPages::hoursText(),
            'confirm' => ! $this->settings()['auto_confirm'],
        ]);
    }

    /** Xem thử bản nháp (link có chữ ký, hết hạn sau 2 giờ): gửi thử không tạo đặt bàn. */
    public function preview(BookingPage $page): Response
    {
        return BookingPages::render($page, test: true);
    }

    /** Thông tin cho script đặt bàn: ngày đặt được, khu vực, số khách tối đa, chữ hiển thị, mã trang. */
    public function info(): JsonResponse
    {
        $settings = $this->settings();
        $today = CarbonImmutable::today();
        $days = [];

        for ($day = $today->addDays((int) $settings['min_notice_days']); $day->lte($today->addDays((int) $settings['max_advance_days'])); $day = $day->addDay()) {
            $hours = tenant()->hoursOn($day);
            $days[] = [
                'date' => $day->toDateString(),
                'long' => $day->isToday() ? __('website.widget.today') : __('ui.calendar.weekdays')[$day->dayOfWeekIso - 1],
                'day' => $day->format('d/m'),
                'today' => $day->isToday(),
                'open' => $hours['closed'] === null,
                'note' => $hours['closed'],
            ];
        }

        // Khách không chọn khu vực: quán xếp. Một bàn ngồi chung một khu nên tối đa bằng khu lớn nhất.
        $largestArea = (int) Area::where('is_active', true)->max('capacity');
        $maxParty = max(1, min(ReservationBooking::onlineSeats($settings), $largestArea));

        return response()->json([
            'online' => (bool) $settings['online_enabled'],
            'closed_message' => __('website.widget.online_off', ['phone' => tenant()->phone ?: '']),
            'confirm' => ! $settings['auto_confirm'],
            'days' => $days,
            'max_party' => $maxParty,
            'token' => Crypt::encryptString(json_encode(['restaurant' => tenant()->id, 'at' => now()->timestamp])),
            'text' => [...__('website.widget'), 'weekdays' => __('ui.calendar.weekdays_short')],
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'party_size' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $slots = $this->booking->onlineSlots(CarbonImmutable::parse($data['date']), (int) $data['party_size']);

        return response()->json([
            'periods' => collect($slots)->groupBy('period')->map(fn ($times, $name) => [
                'name' => $name,
                'times' => $times->map(fn ($slot) => ['time' => $slot['time'], 'available' => $slot['available']])->values(),
            ])->values(),
            'any' => collect($slots)->contains('available', true),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 .()-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:500'],
            'token' => ['required', 'string'],
            'test' => ['nullable', 'string'],
            // Ô bẫy: người không thấy, bot hay điền.
            'website' => ['prohibited'],
        ], [
            'time.required' => __('website.widget.pick_time'),
            'phone.regex' => __('website.widget.bad_phone'),
        ], [
            'name' => __('website.fields.name'),
            'phone' => __('website.fields.phone'),
            'email' => __('website.fields.email'),
        ]);

        $this->assertToken($data['token']);

        if (filled($data['test'] ?? null)) {
            return $this->testSubmit($data);
        }

        $phone = preg_replace('/[^0-9+]/', '', $data['phone']);
        $start = CarbonImmutable::parse($data['date'].' '.$data['time']);

        // Bấm gửi hai lần: trả lại đặt bàn vừa tạo.
        $existing = Reservation::where('source', ReservationSource::Online)->where('reserved_at', $start)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->whereHas('customer', fn ($query) => $query->where('phone', $phone))->first();

        try {
            $reservation = $existing ?? $this->booking->create([
                'area_id' => null,
                'reserved_at' => $start,
                'party_size' => (int) $data['party_size'],
                'customer_name' => trim($data['name']),
                'customer_phone' => $phone,
                'customer_email' => $data['email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'locale' => app()->getLocale(),
            ], ReservationSource::Online);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['time' => collect($exception->errors())->flatten()->all()]);
        }

        return response()->json([
            'ok' => true,
            'status' => $reservation->status->value,
            'message' => __($reservation->status === ReservationStatus::Pending ? 'website.widget.sent_pending' : 'website.widget.sent_confirmed'),
            'url' => $reservation->publicUrl(),
        ]);
    }

    /** Khách xem đặt bàn của mình qua link riêng. */
    public function status(string $token): View
    {
        $reservation = Reservation::with(['customer', 'area'])->where('public_token', $token)->firstOrFail();

        return view('public.booking-status', ['restaurant' => tenant(), 'reservation' => $reservation]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $reservation = Reservation::with('customer')->where('public_token', $token)->firstOrFail();

        if (! $reservation->guestCanCancel()) {
            return back()->with('error', __('website.status.cannot_cancel'));
        }

        $this->booking->changeStatus($reservation, ReservationStatus::Cancelled);

        return back()->with('status', __('website.status.cancelled_done'));
    }

    /** Gửi thử từ trang xem thử: kiểm tra như thật, không tạo đặt bàn, đánh dấu bản nháp đã thử. */
    private function testSubmit(array $data): JsonResponse
    {
        try {
            $test = json_decode(Crypt::decryptString($data['test']), true);
        } catch (DecryptException) {
            $test = null;
        }

        $page = ($test['restaurant'] ?? null) === tenant()->id && ($test['until'] ?? 0) > now()->timestamp ? BookingPage::find($test['page'] ?? 0) : null;
        abort_if($page === null, 403);

        $free = collect($this->booking->onlineSlots(CarbonImmutable::parse($data['date']), (int) $data['party_size']))
            ->contains(fn ($slot) => $slot['time'] === $data['time'] && $slot['available']);

        if (! $free) {
            throw ValidationException::withMessages(['time' => __('website.widget.time_taken')]);
        }

        $page->update(['tested_at' => now()]);

        return response()->json(['ok' => true, 'test' => true, 'status' => 'test', 'message' => __('website.widget.sent_test'), 'url' => null]);
    }

    /** Mã trang: của đúng quán, mở trang ít nhất 2 giây (bot gửi ngay) và chưa quá 6 giờ. */
    private function assertToken(string $token): void
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            $data = null;
        }

        $age = now()->timestamp - (int) ($data['at'] ?? 0);

        if (($data['restaurant'] ?? null) !== tenant()->id || $age < 2 || $age > 6 * 3600) {
            throw ValidationException::withMessages(['token' => __('website.widget.expired')]);
        }
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return $this->modules->settings(tenant(), 'reservation');
    }
}
