<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\Shift;
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

    public function test_back_returns_to_the_day_of_the_booking(): void
    {
        $reservation = $this->book(['reserved_at' => '2026-12-05T19:00']);

        $this->get("$this->base/{$reservation->id}")->assertSee('date=2026-12-05', false);
        $this->get("$this->base/{$reservation->id}/edit")->assertSee('href="'.url("$this->base/{$reservation->id}").'"', false);
    }

    public function test_day_list_and_filters(): void
    {
        $this->book(['customer_name' => 'Khách hôm nay']);
        $this->book(['customer_name' => 'Khách ngày mai', 'customer_phone' => '0902222222', 'reserved_at' => '2026-12-02T19:00']);

        $this->get($this->base)->assertSee('Khách hôm nay')->assertDontSee('Khách ngày mai');
        $this->get("$this->base?date=2026-12-02")->assertSee('Khách ngày mai');
        $this->get("$this->base?status=cancelled")->assertDontSee('Khách hôm nay');
        $this->get("$this->base?date=khong-hop-le")->assertOk();
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

    private function booked(string $at): Reservation
    {
        $restaurant = ['restaurant_id' => $this->restaurant->id];

        return Reservation::create([
            ...$restaurant,
            'customer_id' => Customer::create([...$restaurant, 'name' => 'Khách', 'phone' => '09'.random_int(10000000, 99999999)])->id,
            'area_id' => Area::forRestaurant($this->restaurant)->first()->id,
            'reserved_at' => $at,
            'ends_at' => $at,
            'party_size' => 2,
            'status' => ReservationStatus::Confirmed,
            'source' => ReservationSource::Phone,
        ]);
    }

    public function test_list_uses_the_calendar_and_can_show_all_days_grouped(): void
    {
        $this->booked('2026-12-01 18:00');
        $this->booked('2026-12-03 19:00');
        $this->booked('2026-11-20 12:00');

        $list = "/{$this->restaurant->slug}/admin/reservations";

        $this->get($list)
            ->assertSee('data-date-picker', false)
            ->assertDontSee('type="date"', false)
            ->assertSee(__('reservation.show_all'));

        $this->get("$list?all=1")
            ->assertOk()
            ->assertSee(__('reservation.show_by_day'))
            ->assertDontSee('data-date-picker', false)
            ->assertSeeInOrder(['Hôm nay, Thứ ba 01/12', 'Thứ năm 03/12', 'Thứ sáu 20/11']);
    }

    public function test_online_rules(): void
    {
        $this->restaurant->update(['closed_dates' => [['date' => '2026-12-05', 'note' => 'Nghỉ']]]);

        foreach (range(1, 7) as $day) {
            Shift::create(['restaurant_id' => $this->restaurant->id, 'type' => ShiftType::Opening, 'weekday' => $day, 'name' => 'Cả ngày', 'start_time' => '10:00', 'end_time' => '22:00']);
        }

        $refused = [
            'quá gần' => ['reserved_at' => '2026-12-01T09:30'],
            'quá xa' => ['reserved_at' => '2027-03-01T12:00'],
            'lệch khung giờ' => ['reserved_at' => '2026-12-02T12:10'],
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

        $this->assertSame(ReservationStatus::Pending, $this->book(['reserved_at' => '2026-12-02T12:00'], ReservationSource::Online)->status);
    }

    public function test_area_not_open_for_online_only_blocks_online(): void
    {
        $this->area->update(['online_bookable' => false]);

        $this->expectException(ValidationException::class);

        try {
            $this->book();
            $this->assertTrue(true);
        } finally {
            $this->book(['reserved_at' => '2026-12-02T12:00'], ReservationSource::Online);
        }
    }

    public function test_settings_save(): void
    {
        $settings = "$this->base/settings";

        $this->get($settings)->assertOk();

        $this->put("$settings/booking", [
            'min_notice_minutes' => 30, 'max_advance_days' => 14, 'turn_minutes' => 120, 'slot_interval_minutes' => 15,
        ])->assertSessionHasNoErrors();

        $this->actingInTenant($this->restaurant);
        $this->assertSame('120', (string) app(ModuleManager::class)->settings($this->restaurant, 'reservation')['turn_minutes']);

        $this->post("$settings/closed-dates", ['date' => '2027-02-10', 'note' => 'Tết'])->assertSessionHasNoErrors();
        $this->assertSame('2027-02-10', $this->restaurant->fresh()->closed_dates[0]['date']);

        $this->delete("$settings/closed-dates/2027-02-10");
        $this->assertSame([], $this->restaurant->fresh()->closed_dates);
    }

    public function test_area_management(): void
    {
        $settings = "$this->base/settings";

        $this->post("$settings/areas", ['name' => 'Lầu 1', 'capacity' => 20, 'is_active' => 1, 'online_bookable' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Area::forRestaurant($this->restaurant)->count());

        $this->book();
        $this->delete("$settings/areas/{$this->area->id}")->assertSessionHas('error');

        $this->put("$settings/areas/{$this->area->id}", ['name' => 'Trong nhà', 'capacity' => 5])->assertSessionHasNoErrors();
        $this->assertFalse($this->area->fresh()->is_active);

        $this->post($this->base, $this->form())->assertSessionHasErrors('area_id');
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
