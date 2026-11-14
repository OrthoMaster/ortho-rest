<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Mail\RestaurantMail;
use App\Mail\TemplatedMail;
use App\Models\MailSetting;
use App\Models\MailTemplate;
use App\Models\PushSubscription;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationStatus;
use App\Modules\Website\Models\BookingPage;
use App\Push\WebPush;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Đặt bàn online: trang /dat-ban (mẫu hoặc tự tạo), giờ trống, gửi đặt bàn, link riêng
 * của khách, email cho khách và quán, mục Email, Web đặt trước.
 */
class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private Area $area;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-12-01 09:00'));
        Mail::fake();
        $this->withoutDefer();

        $this->restaurant = Restaurant::factory()->create(['name' => 'Sài Gòn', 'phone' => '028 3822 1234', 'email' => 'lienhe@saigon.vn', 'address' => '45 Lê Thánh Tôn']);
        $this->enableModules($this->restaurant, 'website', 'reservation');
        $this->owner = User::factory()->owner($this->restaurant)->create(['email' => 'chu@saigon.vn']);
        $this->area = Area::forRestaurant($this->restaurant)->sole();
        $this->area->update(['capacity' => 20]);
        $this->site = "/{$this->restaurant->slug}/dat-ban";

        foreach (range(1, 7) as $day) {
            Shift::create(['restaurant_id' => $this->restaurant->id, 'type' => ShiftType::Opening, 'weekday' => $day, 'name' => 'Tối', 'start_time' => '17:00', 'end_time' => '22:00']);
        }
    }

    /** Lấy mã trang như script đặt bàn, chờ qua thời gian tối thiểu. */
    private function token(): string
    {
        $token = $this->getJson("$this->site/thong-tin")->json('token');
        $this->travel(5)->seconds();

        return $token;
    }

    private function submit(array $overrides = []): TestResponse
    {
        return $this->post("$this->site/gui", [
            'name' => 'Nguyễn An',
            'phone' => '0901 234 567',
            'email' => 'an@khach.vn',
            'date' => '2026-12-03',
            'time' => '19:00',
            'party_size' => 4,
            'token' => $this->token(),
            ...$overrides,
        ], ['Accept' => 'application/json']);
    }

    public function test_default_page_and_widget_data(): void
    {
        $this->get($this->site)->assertOk()
            ->assertSee('data-liresto-booking', false)
            ->assertSee('data-liresto-when', false)
            ->assertSee('45 Lê Thánh Tôn')
            ->assertSee(__('website.booking.submit'));

        $info = $this->getJson("$this->site/thong-tin")->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertTrue($info->json('online'));
        $this->assertTrue($info->json('confirm'));
        $this->assertSame('2026-12-01', $info->json('days.0.date'));
        $this->assertSame(20, $info->json('max_party'));
        $this->assertNull($info->json('areas'));
        $this->assertSame(['Hôm nay', true], [$info->json('days.0.long'), $info->json('days.0.today')]);
        $this->assertSame('Thứ năm', $info->json('days.2.long'));
        $this->assertSame('T2', $info->json('text.weekdays.0'));

        // Ca tối 17:00–22:00, lượt bàn 90 phút: 17:00 … 20:30 mỗi 15 phút.
        $slots = $this->getJson("$this->site/gio-trong?date=2026-12-03&party_size=4")->assertOk();
        $times = collect($slots->json('periods.0.times'));
        $this->assertSame(['17:00', '20:30'], [$times->first()['time'], $times->last()['time']]);
        $this->assertSame(15, $times->count());
        $this->assertTrue($slots->json('any'));

        // Trang chủ có nút Đặt bàn.
        $this->get("/{$this->restaurant->slug}")->assertSee(__('website.booking.title'))->assertSee($this->site, false);
    }

    public function test_guest_books_waits_for_confirmation_and_gets_emails(): void
    {
        $sent = $this->fakePush();
        PushSubscription::create(['user_id' => $this->owner->id, 'endpoint' => 'https://push.test/1', 'endpoint_hash' => hash('sha256', '1'), 'public_key' => 'k', 'auth_token' => 'a']);

        $response = $this->submit()->assertOk()->assertJsonPath('status', 'pending');
        $reservation = Reservation::withoutGlobalScopes()->sole();
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertSame('0901234567', $reservation->customer->phone);
        $this->assertStringEndsWith("/dat-ban/{$reservation->public_token}", $response->json('url'));

        // Khách: đã nhận, chờ xác nhận. Quán: có khách đặt (email chủ quán) và thông báo đẩy.
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->key === 'guest_received' && $mail->hasTo('an@khach.vn')
            && str_contains($mail->body, 'chờ quán xác nhận') && $mail->hasReplyTo('lienhe@saigon.vn') && $mail->sender->name === 'Sài Gòn');
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->key === 'staff_new' && $mail->hasTo('chu@saigon.vn') && str_contains($mail->mailSubject, 'Nguyễn An'));
        $this->assertSame([$this->owner->id], $sent[0]['users']);
        $this->assertStringContainsString('Nguyễn An', $sent[0]['title']);

        // Khách mở link: thấy đang chờ; quán xác nhận thì khách nhận email đã xác nhận.
        $this->get($response->json('url'))->assertOk()->assertSee(__('website.status.states.pending'))->assertSee($reservation->code());
        $this->actingAs($this->owner)->post("/{$this->restaurant->slug}/admin/reservations/{$reservation->id}/status", ['status' => 'confirmed']);
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->key === 'guest_confirmed' && $mail->hasTo('an@khach.vn'));

        // Khách tự huỷ: khách nhận email đã huỷ, quán nhận email và thông báo.
        auth()->logout();
        $this->post($response->json('url').'/huy')->assertSessionHas('status', __('website.status.cancelled_done'));
        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->key === 'guest_cancelled');
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->key === 'staff_guest_cancelled' && $mail->hasTo('chu@saigon.vn'));
        $this->assertCount(2, $sent);
        $this->get($response->json('url'))->assertDontSee(__('website.status.cancel_title'));
        $this->post($response->json('url').'/huy')->assertSessionHas('error');
    }

    public function test_no_confirmation_needed_confirms_at_once(): void
    {
        $this->actingAs($this->owner)->put("/{$this->restaurant->slug}/admin/reservations/settings/booking", [
            'turn_minutes' => 90, 'online_enabled' => 1, 'min_notice_days' => 0, 'max_advance_days' => 30, 'online_seats' => 20,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(app(ModuleManager::class)->settings($this->restaurant, 'reservation')['auto_confirm']);
        auth()->logout();

        $this->submit()->assertJsonPath('status', 'confirmed');
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->key === 'guest_confirmed');
        Mail::assertNotSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->key === 'guest_received');
    }

    public function test_bots_and_bad_requests_are_refused(): void
    {
        // Gửi ngay khi mở trang (bot), mã của quán khác, ô bẫy có chữ.
        $fresh = $this->getJson("$this->site/thong-tin")->json('token');
        $this->post("$this->site/gui", ['name' => 'A', 'phone' => '0901234567', 'date' => '2026-12-03', 'time' => '19:00', 'party_size' => 2, 'token' => $fresh], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $other = Crypt::encryptString(json_encode(['restaurant' => 999, 'at' => now()->timestamp - 60]));
        $this->submit(['token' => $other])->assertJsonValidationErrors('token');
        $this->submit(['website' => 'http://spam'])->assertJsonValidationErrors('website');

        // Giờ ngoài ca, thiếu giờ, số điện thoại sai.
        $this->submit(['time' => '15:00'])->assertJsonValidationErrors('time');
        $this->submit(['time' => ''])->assertJsonValidationErrors('time');
        $this->submit(['phone' => 'abc'])->assertJsonValidationErrors('phone')->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertSame(0, Reservation::withoutGlobalScopes()->count());

        // Bấm gửi hai lần: chỉ một đặt bàn.
        $this->submit()->assertOk();
        $this->submit()->assertOk();
        $this->assertSame(1, Reservation::withoutGlobalScopes()->count());

        // Tắt đặt online: trang báo gọi quán.
        app(ModuleManager::class)->updateSettings($this->restaurant, 'reservation', ['online_enabled' => false]);
        $this->assertFalse($this->getJson("$this->site/thong-tin")->json('online'));
        $this->submit(['date' => '2026-12-04'])->assertJsonValidationErrors('time');
    }

    public function test_custom_page_draft_test_publish_and_sandbox(): void
    {
        $admin = "/{$this->restaurant->slug}/admin/website/dat-ban";
        $this->actingAs($this->owner)->get("/{$this->restaurant->slug}/admin/website")->assertRedirect($admin);
        $this->get($admin)->assertOk()->assertSee(__('website.admin.copy_prompt'))->assertSee('{{ten_quan}}', false)->assertSee('028 3822 1234');

        // Thiếu ô bắt buộc, có ô mật khẩu: lỗi, không xem thử được.
        $this->post("$admin/upload", ['html' => '<html><body><form data-liresto-booking><input name="name"><input type="password" name="pw"><button>Gửi</button></form></body></html>']);
        $draft = BookingPage::withoutGlobalScopes()->sole();
        $this->assertTrue($draft->hasErrors());
        $this->get($admin)->assertSee(__('website.check.field_missing', ['field' => __('website.fields.phone')]))->assertSee(__('website.check.sensitive'));
        $this->get("$admin/xem-thu")->assertNotFound();

        $html = '<!doctype html><html><head><title>{{ten_quan}}</title></head><body><h1>{{ten_quan}}</h1><p>{{so_dien_thoai}}</p>'
            .'<form data-liresto-booking><div data-liresto-when></div><input name="name"><input name="phone"><button type="submit">Đặt</button></form></body></html>';
        $this->post("$admin/upload", ['file' => UploadedFile::fake()->createWithContent('trang.html', $html)])->assertSessionHas('status');
        $draft = BookingPage::withoutGlobalScopes()->sole();
        $this->assertFalse($draft->hasErrors());

        // Chưa gửi thử: chưa đăng được.
        $this->post("$admin/publish")->assertSessionHas('error', __('website.admin.cannot_publish'));

        // Xem thử: link có chữ ký, chạy cô lập, gửi thử không tạo đặt bàn.
        $preview = $this->get("$admin/xem-thu")->assertRedirect()->headers->get('Location');
        $page = $this->get($preview)->assertOk();
        $this->assertStringContainsString('sandbox allow-scripts', $page->headers->get('Content-Security-Policy'));
        $page->assertSee('<h1>Sài Gòn</h1>', false)->assertSee('028 3822 1234')->assertSee('booking-widget.js', false)->assertSee(__('website.footer.simple'));
        $this->get(str_replace('signature=', 'signature=x', $preview))->assertForbidden();

        preg_match('/"test":"([^"]+)"/', $page->getContent(), $match);
        $this->submit(['test' => stripslashes($match[1])])->assertOk()->assertJsonPath('test', true);
        $this->assertSame(0, Reservation::withoutGlobalScopes()->count());
        $this->assertNotNull($draft->fresh()->tested_at);

        // Đăng: khách thấy trang tự tạo; trang mẫu vẫn mở ở link dự phòng.
        $this->actingAs($this->owner)->post("$admin/publish")->assertSessionHas('status', __('website.admin.published'));
        auth()->logout();
        $live = $this->get($this->site)->assertOk()->assertSee('<h1>Sài Gòn</h1>', false);
        $this->assertStringContainsString('sandbox', $live->headers->get('Content-Security-Policy'));
        $this->get("$this->site/mac-dinh")->assertOk()->assertSee(__('website.booking.lead'));

        // Quay về trang mẫu; lấy lại bản đã đăng làm bản nháp mới (phải thử lại).
        $this->actingAs($this->owner)->put("$admin/mode", ['mode' => 'default'])->assertSessionHas('status');
        $this->get($this->site)->assertSee(__('website.booking.lead'));
        $this->post("$admin/versions/{$draft->id}/restore")->assertSessionHas('status');
        $this->assertNull(BookingPage::withoutGlobalScopes()->where('status', BookingPage::DRAFT)->sole()->tested_at);
    }

    public function test_website_tab_needs_both_modules(): void
    {
        app(ModuleManager::class)->disable($this->restaurant, 'reservation');
        $this->actingAs($this->owner)->get("/{$this->restaurant->slug}/admin/website")->assertOk()->assertSee(__('ui.placeholder.lead'));
        $this->get("/{$this->restaurant->slug}/admin/website/dat-ban")->assertNotFound();
        $this->get($this->site)->assertNotFound();
        $this->get("/{$this->restaurant->slug}/admin/email")->assertNotFound();
    }

    public function test_mail_server_and_templates(): void
    {
        $base = "/{$this->restaurant->slug}/admin/email";
        $this->actingAs($this->owner)->get($base)->assertOk()->assertSee(__('mail.via_system'));

        $this->put($base, ['use_smtp' => 1, 'smtp_host' => 'smtp.saigon.vn', 'smtp_port' => 587, 'smtp_encryption' => 'tls', 'smtp_username' => 'datban', 'smtp_password' => 'bimat', 'from_address' => 'datban@saigon.vn', 'notify_emails' => "quanly@saigon.vn\nchu@saigon.vn"])
            ->assertSessionHasNoErrors();
        $settings = MailSetting::withoutGlobalScopes()->sole();
        $this->assertTrue($settings->usesSmtp());
        $this->assertSame('bimat', $settings->smtp_password);
        $this->assertNotSame('bimat', $settings->getRawOriginal('smtp_password'));
        $this->get($base)->assertDontSee('bimat');

        // Để trống mật khẩu thì giữ mật khẩu cũ; email sai thì báo.
        $this->put($base, ['use_smtp' => 1, 'smtp_host' => 'smtp.saigon.vn', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'from_address' => 'datban@saigon.vn', 'notify_emails' => 'quanly@saigon.vn']);
        $this->assertSame('bimat', $settings->fresh()->smtp_password);
        $this->put($base, ['notify_emails' => 'khong-phai-email'])->assertSessionHasErrors('notify_emails');

        app(TenantContext::class)->set($this->restaurant);
        $this->assertSame(['quanly@saigon.vn'], RestaurantMail::staffEmails());
        $transport = RestaurantMail::mailer($settings->fresh())->getSymfonyTransport();
        $this->assertStringContainsString('smtps://smtp.saigon.vn', (string) $transport);

        // Sửa mẫu: bỏ thẻ lạ, giữ biến; về mẫu mặc định.
        $this->get("$base/mau")->assertOk()->assertSee(__('mail.templates.guest_confirmed.name'));
        $this->get("$base/mau/guest_confirmed")->assertOk()->assertSee('data-var="ten_khach"', false);
        $this->put("$base/mau/guest_confirmed", ['subject' => 'Hẹn {{ten_khach}} lúc {{gio}}', 'body' => '<p onclick="x()">Chào <b>{{ten_khach}}</b><script>alert(1)</script></p>'])
            ->assertSessionHas('status');
        $template = MailTemplate::withoutGlobalScopes()->sole();
        $this->assertSame('<p>Chào <b>{{ten_khach}}</b></p>', $template->body);

        $mail = RestaurantMail::render(RestaurantMail::template('guest_confirmed'), ['ten_khach' => '<An>', 'gio' => '19:00']);
        $this->assertSame('Hẹn <An> lúc 19:00', $mail['subject']);
        $this->assertStringContainsString('Chào <b>&lt;An&gt;</b>', $mail['html']);

        // Gửi thử: máy chủ riêng không kết nối được thì báo lỗi; dùng email hệ thống thì gửi được.
        $this->post("$base/mau/guest_confirmed/test", ['to' => 'thu@saigon.vn'])->assertSessionHas('error');
        MailSetting::withoutGlobalScopes()->update(['use_smtp' => false]);
        $this->post("$base/mau/guest_confirmed/test", ['to' => 'thu@saigon.vn'])->assertSessionHas('status');
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('thu@saigon.vn') && $mail->mailSubject === 'Hẹn Nguyễn Văn An lúc 19:00');
        $this->delete("$base/mau/guest_confirmed")->assertSessionHas('status', __('mail.reset_done'));
        $this->assertSame(0, MailTemplate::withoutGlobalScopes()->count());
        $this->get("$base/mau/khong_co")->assertNotFound();

        // Nhân viên không có quyền cài đặt thì không vào được.
        $this->actingAs($this->member($this->restaurant))->get($base)->assertForbidden();
    }

    public function test_owner_sets_contact_info(): void
    {
        $this->actingAs($this->owner)->put("/{$this->restaurant->slug}/admin/restaurant/contact", ['phone' => '0909 111 222', 'email' => 'moi@saigon.vn', 'address' => '1 Đồng Khởi'])
            ->assertSessionHas('status', __('ui.restaurant.contact_saved'));
        $this->assertSame(['0909 111 222', 'moi@saigon.vn', '1 Đồng Khởi'], [$this->restaurant->fresh()->phone, $this->restaurant->fresh()->email, $this->restaurant->fresh()->address]);
        $this->put("/{$this->restaurant->slug}/admin/restaurant/contact", ['phone' => 'abc'])->assertSessionHasErrors('phone');
    }

    private function fakePush(): \ArrayObject
    {
        config(['liresto.push.public_key' => 'public', 'liresto.push.private_key' => 'private']);
        $sent = new \ArrayObject;
        $this->app->instance(WebPush::class, new class($sent) extends WebPush
        {
            public function __construct(private \ArrayObject $log) {}

            public function send(Collection $subscriptions, array $message): void
            {
                if ($subscriptions->isNotEmpty()) {
                    $this->log[] = ['users' => $subscriptions->pluck('user_id')->all(), ...$message];
                }
            }
        });

        return $sent;
    }
}
