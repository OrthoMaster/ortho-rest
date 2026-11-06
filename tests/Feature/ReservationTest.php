<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Enums\SpecialDayType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\SpecialDay;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationBooking;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use App\Modules\Reservation\ReservationStatusNotification;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private Area $area;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-12-01 09:00'));

        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'reservation');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->base = "/{$this->restaurant->slug}/admin/reservations";

        $this->area = Area::forRestaurant($this->restaurant)->sole();
        $this->area->update(['capacity' => 10]);

        $this->actingAs($this->owner);
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return [
            'customer_name' => 'Anh Minh',
            'customer_phone' => '0901234567',
            'area_id' => $this->area->id,
            'reserved_at' => '2026-12-01T19:00',
            'party_size' => 4,
            'source' => 'phone',
            ...$overrides,
        ];
    }

    private function book(array $overrides = [], ReservationSource $source = ReservationSource::Phone): Reservation
    {
        $this->actingInTenant($this->restaurant);

        return app(ReservationBooking::class)->create($this->form($overrides), $source, $this->owner);
    }

    public function test_module_installer_creates_a_first_area_once(): void
    {
        $this->enableModules($this->restaurant, 'reservation');

        $this->assertSame(1, Area::forRestaurant($this->restaurant)->count());
    }

    public function test_staff_can_enter_a_phone_booking_and_it_records_who(): void
    {
        $this->post($this->base, $this->form())->assertSessionHasNoErrors()->assertRedirect();

        $reservation = Reservation::forRestaurant($this->restaurant)->sole();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame($this->owner->id, $reservation->created_by);
        $this->assertSame('2026-12-01 20:30', $reservation->ends_at->format('Y-m-d H:i'));
    }

    public function test_walk_in_starts_seated(): void
    {
        $this->post($this->base, $this->form(['source' => 'walk_in']));

        $this->assertSame(ReservationStatus::Seated, Reservation::forRestaurant($this->restaurant)->sole()->status);
    }

    public function test_full_area_is_refused(): void
    {
        $this->book(['party_size' => 8]);

        $this->post($this->base, $this->form(['party_size' => 3]))->assertSessionHasErrors('reserved_at');
        $this->post($this->base, $this->form(['party_size' => 2]))->assertSessionHasNoErrors();
    }

    public function test_party_bigger_than_the_area_is_refused(): void
    {
        $this->post($this->base, $this->form(['party_size' => 11]))->assertSessionHasErrors('reserved_at');
    }

    public function test_bookings_that_do_not_overlap_share_the_seats(): void
    {
        $this->book(['reserved_at' => '2026-12-01T18:00', 'party_size' => 10]);

        $this->post($this->base, $this->form(['reserved_at' => '2026-12-01T19:30', 'party_size' => 10]))
            ->assertSessionHasNoErrors();
    }

    public function test_peak_is_used_rather_than_the_sum_of_everyone_in_the_window(): void
    {
        $this->book(['reserved_at' => '2026-12-01T18:00', 'party_size' => 6]);
        $this->book(['reserved_at' => '2026-12-01T19:30', 'party_size' => 6]);

        // 18:30 đến 20:00 chạm cả hai bàn trên, nhưng hai bàn không ngồi cùng lúc.
        $this->post($this->base, $this->form(['reserved_at' => '2026-12-01T18:30', 'party_size' => 4]))
            ->assertSessionHasNoErrors();
    }

    public function test_cancelled_booking_frees_its_seats(): void
    {
        $reservation = $this->book(['party_size' => 10]);

        $this->post("$this->base/{$reservation->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();

        $this->post($this->base, $this->form(['party_size' => 10]))->assertSessionHasNoErrors();
    }

    public function test_full_status_path_is_logged_with_who_and_when(): void
    {
        $reservation = $this->book([], ReservationSource::Online);

        foreach (['confirmed', 'seated', 'completed'] as $status) {
            $this->post("$this->base/{$reservation->id}/status", ['status' => $status])->assertSessionHasNoErrors();
        }

        $this->actingInTenant($this->restaurant);
        $logs = $reservation->statusLogs()->get();

        $this->assertSame(
            ['pending', 'confirmed', 'seated', 'completed'],
            $logs->map(fn ($log) => $log->to_status->value)->all(),
        );
        $this->assertSame($this->owner->id, $logs->last()->user_id);
        $this->assertSame('confirmed', $logs[1]->to_status->value);
        $this->assertSame('pending', $logs[1]->from_status->value);
    }

    public function test_wrong_status_jump_is_refused(): void
    {
        $reservation = $this->book();

        $this->post("$this->base/{$reservation->id}/status", ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->post("$this->base/{$reservation->id}/status", ['status' => 'cancelled']);
        $this->post("$this->base/{$reservation->id}/status", ['status' => 'confirmed'])->assertSessionHasErrors('status');
    }

    public function test_edit_ignores_its_own_seats_and_still_checks_capacity(): void
    {
        $reservation = $this->book(['party_size' => 8]);
        $this->book(['party_size' => 2]);

        $this->put("$this->base/{$reservation->id}", $this->form(['party_size' => 8, 'notes' => 'Sửa ghi chú']))
            ->assertSessionHasNoErrors();
        $this->put("$this->base/{$reservation->id}", $this->form(['party_size' => 9]))
            ->assertSessionHasErrors('reserved_at');
    }

    public function test_finished_booking_cannot_be_edited(): void
    {
        $reservation = $this->book();
        $this->post("$this->base/{$reservation->id}/status", ['status' => 'cancelled']);

        $this->put("$this->base/{$reservation->id}", $this->form())->assertSessionHasErrors('reserved_at');
    }

    public function test_same_phone_is_one_customer_with_counts(): void
    {
        $first = $this->book(['customer_phone' => '0901234567']);
        $this->book(['reserved_at' => '2026-12-02T19:00']);
        $this->post("$this->base/{$first->id}/status", ['status' => 'no_show']);

        $this->post($this->base, $this->form(['customer_phone' => '+84 901 234 567']))->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::forRestaurant($this->restaurant)->count());

        $this->get("$this->base/customers")->assertOk()->assertSeeInOrder(['Anh Minh', '0901234567', '3', '0', '1']);
    }

    public function test_same_phone_in_two_restaurants_is_two_customers(): void
    {
        $this->book();

        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'reservation');
        $this->actingInTenant($other);
        Customer::remember('Khách quán khác', '0901234567');

        $this->assertSame('Anh Minh', Customer::forRestaurant($this->restaurant)->sole()->name);
    }

    public function test_back_goes_to_today_or_to_the_day_in_manage(): void
    {
        $today = $this->book();
        $later = $this->book(['reserved_at' => '2026-12-05T19:00', 'customer_phone' => '0902222222']);

        $this->get("$this->base/{$today->id}")->assertSee('href="'.url($this->base).'"', false);
        $this->get("$this->base/{$later->id}")->assertSee('from=2026-12-05', false);
        $this->get("$this->base/{$later->id}/edit")->assertSee('href="'.url("$this->base/{$later->id}").'"', false);
    }

    public function test_today_shows_only_accepted_bookings_of_today(): void
    {
        $this->book(['customer_name' => 'Khách đã nhận']);
        $this->book(['customer_name' => 'Khách chờ', 'customer_phone' => '0903333333', 'party_size' => 2], ReservationSource::Online);
        $this->book(['customer_name' => 'Khách ngày mai', 'customer_phone' => '0902222222', 'reserved_at' => '2026-12-02T19:00']);

        $this->get($this->base)
            ->assertSee('Khách đã nhận')
            ->assertDontSee('Khách chờ')
            ->assertDontSee('Khách ngày mai')
            ->assertSee(__('reservation.pending_today', ['count' => 1]));
    }

    public function test_manage_lists_upcoming_by_default_and_filters(): void
    {
        $this->booked('2026-11-20 12:00', 'Khách tuần trước');
        $this->book(['customer_name' => 'Khách hôm nay']);
        $this->book(['customer_name' => 'Khách chờ', 'customer_phone' => '0903333333', 'party_size' => 2, 'reserved_at' => '2026-12-03T19:00'], ReservationSource::Online);
        $manage = "$this->base/manage";

        $this->get($manage)
            ->assertSeeInOrder(['Hôm nay, Thứ ba 01/12', 'Khách hôm nay', 'Thứ năm 03/12', 'Khách chờ'])
            ->assertDontSee('Khách tuần trước')
            ->assertSee('<span class="subnav-count">1</span>', false);

        $this->get("$manage?upcoming=0&from=2026-11-15&to=2026-11-30")->assertSee('Khách tuần trước')->assertDontSee('Khách hôm nay');
        $this->get("$manage?status[]=pending")->assertSee('Khách chờ')->assertDontSee('Khách hôm nay');
        $this->get("$manage?source[]=online&source[]=walk_in")->assertSee('Khách chờ')->assertDontSee('Khách hôm nay');
        $this->get("$manage?q=0903")->assertSee('Khách chờ')->assertDontSee('Khách hôm nay');
        $this->get("$manage?q=h%C3%B4m")->assertSee('Khách hôm nay')->assertDontSee('Khách chờ');

        // Khoảng dài quá 92 ngày thì cắt bớt.
        $this->get("$manage?upcoming=0&from=2026-01-01&to=2026-12-31")->assertSee('name="to" value="2026-04-02"', false);
    }

    public function test_staff_sees_today_but_cannot_manage_or_accept(): void
    {
        $staff = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Staff]);
        $pending = $this->book(['party_size' => 2], ReservationSource::Online);
        $confirmed = $this->book(['customer_phone' => '0903333333', 'party_size' => 2]);

        $this->actingAs($staff);
        $this->get($this->base)->assertOk()->assertDontSee(__('reservation.manage'));
        $this->get("$this->base/manage")->assertForbidden();

        $this->post("$this->base/{$pending->id}/status", ['status' => 'confirmed'])->assertForbidden();
        $this->post("$this->base/{$confirmed->id}/status", ['status' => 'seated'])->assertSessionHasNoErrors();
        $this->get("$this->base/{$pending->id}")->assertDontSee(__('reservation.action.confirmed'));
    }

    public function test_owner_can_give_manage_rights_to_staff(): void
    {
        $staff = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Staff]);
        $pending = $this->book(['party_size' => 2], ReservationSource::Online);

        $this->put("$this->base/settings/booking", ['turn_minutes' => 90, 'online_enabled' => 0, 'manage_roles' => ['staff']])->assertSessionHasNoErrors();

        $this->actingAs($staff)->get("$this->base/manage")->assertOk();
        $this->post("$this->base/{$pending->id}/status", ['status' => 'confirmed'])->assertSessionHasNoErrors();

        // Quản lý mất quyền vì chủ quán chỉ giao cho nhân viên, và không tự giao lại được.
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Manager]);
        $this->actingAs($manager)->get("$this->base/manage")->assertForbidden();
        $this->put("$this->base/settings/booking", ['turn_minutes' => 90, 'online_enabled' => 0, 'manage_roles' => ['manager']]);
        $this->get("$this->base/manage")->assertForbidden();
    }

    public function test_history_records_who_created_changed_and_edited(): void
    {
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Manager, 'name' => 'Quản lý Lan']);
        $reservation = $this->book([], ReservationSource::Online);
        $edit = $this->form(['reserved_at' => '2026-12-01T19:30', 'party_size' => 6, 'notes' => 'Ghế em bé']);

        $this->actingAs($manager)->post("$this->base/{$reservation->id}/status", ['status' => 'confirmed']);
        $this->put("$this->base/{$reservation->id}", $edit)->assertSessionHasNoErrors();
        $this->put("$this->base/{$reservation->id}", $edit);

        $this->actingInTenant($this->restaurant);
        $saved = $reservation->edits()->sole();
        $this->assertSame($manager->id, $saved->user_id);
        $this->assertSame(['Giờ: 19:00 01/12 → 19:30 01/12', 'Số khách: 4 → 6', 'Đổi ghi chú'], $saved->changes);

        $this->get("$this->base/{$reservation->id}")->assertSeeInOrder([
            __('reservation.history_created', ['source' => ReservationSource::Online->label(), 'status' => ReservationStatus::Pending->label()]),
            $this->owner->name,
            'Chờ xác nhận → Đã xác nhận',
            'Quản lý Lan',
            __('reservation.history_edited'),
            'Số khách: 4 → 6',
        ]);
    }

    public function test_confirmation_mail_is_sent_only_with_an_email(): void
    {
        Notification::fake();
        $this->withoutDefer();

        $this->book(['customer_email' => 'khach@mail.vn']);
        $this->book(['customer_name' => 'Không email', 'customer_phone' => '0903333333']);

        Notification::assertSentTimes(ReservationStatusNotification::class, 1);
        Notification::assertSentTo(new AnonymousNotifiable, ReservationStatusNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'khach@mail.vn');
    }

    private function booked(string $at, string $name = 'Khách'): Reservation
    {
        $restaurant = ['restaurant_id' => $this->restaurant->id];

        return Reservation::create([
            ...$restaurant,
            'customer_id' => Customer::create([...$restaurant, 'name' => $name, 'phone' => '09'.random_int(10000000, 99999999)])->id,
            'area_id' => Area::forRestaurant($this->restaurant)->first()->id,
            'reserved_at' => $at,
            'ends_at' => $at,
            'party_size' => 2,
            'status' => ReservationStatus::Confirmed,
            'source' => ReservationSource::Phone,
        ]);
    }

    private function settings(array $values): void
    {
        app(ModuleManager::class)->updateSettings($this->restaurant, 'reservation', $values);
    }

    public function test_online_rules(): void
    {
        SpecialDay::create(['restaurant_id' => $this->restaurant->id, 'date' => '2026-12-05', 'type' => SpecialDayType::Closed, 'periods' => [], 'reason' => 'Nghỉ']);
        $this->settings(['min_notice_days' => 1, 'max_advance_days' => 30]);

        foreach (range(1, 7) as $day) {
            Shift::create(['restaurant_id' => $this->restaurant->id, 'type' => ShiftType::Opening, 'weekday' => $day, 'name' => 'Cả ngày', 'start_time' => '10:00', 'end_time' => '22:00']);
        }

        $refused = [
            'trong ngày khi cần đặt trước 1 ngày' => ['reserved_at' => '2026-12-01T19:00'],
            'quá xa' => ['reserved_at' => '2027-01-01T12:00'],
            'ngoài giờ mở cửa' => ['reserved_at' => '2026-12-02T21:00'],
            'ngày đóng cửa' => ['reserved_at' => '2026-12-05T12:00'],
        ];

        foreach ($refused as $case => $overrides) {
            try {
                $this->book($overrides, ReservationSource::Online);
                $this->fail("Phải từ chối: $case");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        // Không còn khung giờ cố định: 12:10 vẫn đặt được. Ngày cuối cùng được phép cũng được.
        $this->assertSame(ReservationStatus::Pending, $this->book(['reserved_at' => '2026-12-02T12:10'], ReservationSource::Online)->status);
        $this->assertSame(ReservationStatus::Pending, $this->book(['reserved_at' => '2026-12-31T12:00', 'party_size' => 2], ReservationSource::Online)->status);

        // Nhân viên nhập tay thì không bị quy định online chặn.
        $this->assertSame(ReservationStatus::Confirmed, $this->book(['reserved_at' => '2026-12-05T12:00', 'party_size' => 2])->status);
    }

    public function test_same_day_online_booking_when_notice_is_zero(): void
    {
        $this->settings(['min_notice_days' => 0]);

        $this->assertSame(ReservationStatus::Pending, $this->book(['reserved_at' => '2026-12-01T19:00'], ReservationSource::Online)->status);

        $this->expectException(ValidationException::class);
        $this->book(['reserved_at' => '2026-12-01T08:00'], ReservationSource::Online);
    }

    public function test_online_switched_off_refuses_only_online(): void
    {
        $this->settings(['online_enabled' => false]);

        $this->assertSame(ReservationStatus::Confirmed, $this->book()->status);

        $this->expectExceptionMessage(__('reservation.online_off'));
        $this->book(['party_size' => 2], ReservationSource::Online);
    }

    public function test_auto_confirm_skips_pending(): void
    {
        $this->settings(['auto_confirm' => true]);

        $this->assertSame(ReservationStatus::Confirmed, $this->book([], ReservationSource::Online)->status);
    }

    public function test_online_seats_cap_counts_only_online_bookings(): void
    {
        Area::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Ngoài sân', 'capacity' => 10, 'sort_order' => 2]);
        $this->settings(['online_seats' => 6]);

        $this->book(['party_size' => 4], ReservationSource::Online);
        $this->book(['party_size' => 4]);

        try {
            $this->book(['party_size' => 3, 'reserved_at' => '2026-12-01T19:30'], ReservationSource::Online);
            $this->fail('Vượt số chỗ online');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('chỉ còn 2 chỗ', $exception->getMessage());
        }

        $this->assertSame(ReservationStatus::Pending, $this->book(['party_size' => 2, 'reserved_at' => '2026-12-01T19:30'], ReservationSource::Online)->status);
        // Lượt online đã hết giờ thì chỗ được trả lại.
        $this->assertSame(ReservationStatus::Pending, $this->book(['party_size' => 6, 'reserved_at' => '2026-12-01T21:00'], ReservationSource::Online)->status);
    }

    public function test_online_seats_never_exceed_total_seats(): void
    {
        $this->settings(['online_seats' => 50]);

        $this->expectExceptionMessage('chỉ còn 10 chỗ');
        $this->book(['party_size' => 11], ReservationSource::Online);
    }

    public function test_settings_save(): void
    {
        $settings = "$this->base/settings";

        $this->get($settings)->assertOk()->assertSee(__('reservation.online_rules'));

        $this->put("$settings/booking", [
            'turn_minutes' => 120, 'online_enabled' => 1, 'min_notice_days' => 1, 'max_advance_days' => 14, 'auto_confirm' => 1, 'online_seats' => 8,
        ])->assertSessionHasNoErrors();

        $saved = app(ModuleManager::class)->settings($this->restaurant, 'reservation');
        $this->assertSame([120, true, 1, 14, true, 8, 10], [
            $saved['turn_minutes'], $saved['online_enabled'], $saved['min_notice_days'], $saved['max_advance_days'], $saved['auto_confirm'], $saved['online_seats'], $saved['online_seats_total'],
        ]);

        $this->put("$settings/booking", ['turn_minutes' => 120, 'online_enabled' => 1, 'min_notice_days' => 0, 'max_advance_days' => 14, 'online_seats' => 11])
            ->assertSessionHasErrors(['online_seats' => __('reservation.online_seats_over', ['total' => 10])]);
        $this->put("$settings/booking", ['turn_minutes' => 120, 'online_enabled' => 1, 'min_notice_days' => 5, 'max_advance_days' => 3, 'online_seats' => 5])
            ->assertSessionHasErrors('max_advance_days');

        // Tắt đặt online thì không cần các ô quy định, và quy định cũ được giữ.
        $this->put("$settings/booking", ['turn_minutes' => 90, 'online_enabled' => 0])->assertSessionHasNoErrors();
        $saved = app(ModuleManager::class)->settings($this->restaurant, 'reservation');
        $this->assertFalse($saved['online_enabled']);
        $this->assertSame(8, $saved['online_seats']);
    }

    public function test_settings_warn_when_total_seats_changed_since_saved(): void
    {
        $this->settings(['online_seats' => 8, 'online_seats_total' => 10]);
        $this->area->update(['capacity' => 6]);

        $this->get("$this->base/settings")->assertSee(__('reservation.total_changed_since', ['from' => 10, 'to' => 6]));
    }

    public function test_area_management(): void
    {
        $areas = "$this->base/areas";

        $this->get($areas)->assertOk()->assertSee('<table>', false)->assertSee(__('reservation.area_capacity'));

        $this->post($areas, ['name' => 'Lầu 1', 'capacity' => 20])->assertSessionHasNoErrors();
        $upstairs = Area::forRestaurant($this->restaurant)->where('name', 'Lầu 1')->sole();
        $this->assertSame(2, $upstairs->sort_order);

        $this->post("$areas/reorder", ['ids' => [$upstairs->id, $this->area->id]])->assertNoContent();
        $this->get($areas)->assertSeeInOrder(['Lầu 1', $this->area->name]);

        $this->put("$areas/{$this->area->id}", ['name' => 'Trong nhà', 'capacity' => 5])->assertSessionHasNoErrors();
        $this->assertSame('Trong nhà', $this->area->fresh()->name);

        // Còn đặt bàn sắp tới: không xoá được.
        $this->book(['party_size' => 2]);
        $this->delete("$areas/{$this->area->id}")->assertSessionHas('error');

        // Chỉ còn đặt bàn đã qua: ẩn khu vực, giữ lịch sử.
        $this->travelTo(Carbon::parse('2026-12-03 09:00'));
        $this->delete("$areas/{$this->area->id}")->assertSessionHas('status');
        $this->assertFalse($this->area->fresh()->is_active);
        $this->get($areas)->assertDontSee('Trong nhà');

        // Chưa có đặt bàn: xoá hẳn.
        $this->delete("$areas/{$upstairs->id}");
        $this->assertNull(Area::forRestaurant($this->restaurant)->find($upstairs->id));

        $this->post($this->base, $this->form(['reserved_at' => '2026-12-03T19:00']))->assertSessionHasErrors('area_id');
    }

    public function test_changing_total_seats_warns_when_online_is_on(): void
    {
        $this->settings(['online_seats' => 8, 'online_seats_total' => 10]);

        $this->put("$this->base/areas/{$this->area->id}", ['name' => $this->area->name, 'capacity' => 6])
            ->assertSessionHas('warning', __('reservation.total_changed_over', ['from' => 10, 'to' => 6, 'online' => 8]));

        $this->settings(['online_enabled' => false]);
        $this->put("$this->base/areas/{$this->area->id}", ['name' => $this->area->name, 'capacity' => 12])->assertSessionMissing('warning');
    }

    public function test_staff_cannot_manage_areas(): void
    {
        $this->actingAs(User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Staff]))->get("$this->base/areas")->assertForbidden();
    }

    public function test_staff_can_book_but_not_change_settings(): void
    {
        $staff = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Staff]);

        $this->actingAs($staff)->post($this->base, $this->form())->assertSessionHasNoErrors();
        $this->actingAs($staff)->get("$this->base/settings")->assertForbidden();
    }

    public function test_another_restaurants_data_cannot_be_reached(): void
    {
        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'reservation');
        $this->actingInTenant($other);
        $otherArea = Area::forRestaurant($other)->sole();
        $otherBooking = app(ReservationBooking::class)->create($this->form(['area_id' => $otherArea->id]), ReservationSource::Phone);
        app(TenantContext::class)->forget();

        $this->get("$this->base/{$otherBooking->id}")->assertNotFound();
        $this->post("$this->base/{$otherBooking->id}/status", ['status' => 'cancelled'])->assertNotFound();
        $this->put("$this->base/settings/areas/{$otherArea->id}", ['name' => 'X', 'capacity' => 1])->assertNotFound();
        $this->post($this->base, $this->form(['area_id' => $otherArea->id]))->assertSessionHasErrors('area_id');
    }
}
