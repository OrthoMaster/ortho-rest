<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Enums\SpecialDayType;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\SpecialDay;
use App\Models\User;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\ReservationBooking;
use App\Modules\Reservation\ReservationSource;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialDayTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        // 01/12/2026 là thứ ba; 07/12 là thứ hai.
        $this->travelTo(Carbon::parse('2026-12-01 09:00'));
        $this->restaurant = Restaurant::factory()->create();
        $this->base = "/{$this->restaurant->slug}/admin/restaurant/special-days";
        $this->actingAs(User::factory()->owner($this->restaurant)->create());

        foreach (range(1, 6) as $day) {
            $this->shift($day, 'Trưa', '10:00', '14:00');
            $this->shift($day, 'Tối', '17:00', '22:00');
        }
    }

    private function shift(int $day, string $name, string $start, string $end): void
    {
        Shift::create(['restaurant_id' => $this->restaurant->id, 'type' => ShiftType::Opening, 'weekday' => $day, 'name' => $name, 'start_time' => $start, 'end_time' => $end]);
    }

    private function open(string $from, string $to): bool
    {
        return $this->restaurant->isOpenBetween(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
    }

    private function day(string $date): SpecialDay
    {
        return SpecialDay::forRestaurant($this->restaurant)->whereDate('date', $date)->sole();
    }

    public function test_close_whole_day_with_reason(): void
    {
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'closed', 'whole_day' => 1, 'reason' => 'Sửa bếp'])
            ->assertRedirect($this->base)
            ->assertSessionHasNoErrors();

        $day = $this->day('2026-12-07');
        $this->assertTrue($day->isWholeDay());
        $this->assertSame('Sửa bếp', $day->reason);
        $this->assertFalse($this->open('2026-12-07 11:00', '2026-12-07 12:00'));
        $this->assertTrue($this->open('2026-12-08 11:00', '2026-12-08 12:00'));

        $this->get($this->base)->assertSee('Thứ hai 07/12')->assertSee('Sửa bếp')->assertSee(__('special_days.whole_day'));
    }

    public function test_close_only_some_shifts(): void
    {
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'closed', 'closed_shifts' => ['Tối|17:00|22:00']])->assertSessionHasNoErrors();

        $this->assertTrue($this->open('2026-12-07 11:00', '2026-12-07 12:00'));
        $this->assertFalse($this->open('2026-12-07 18:00', '2026-12-07 19:00'));
        $this->assertSame('Tối 17:00 – 22:00', $this->day('2026-12-07')->periodsText());
    }

    public function test_closing_needs_a_shift_of_that_weekday(): void
    {
        // Chủ nhật 06/12 không có ca nào.
        $this->post($this->base, ['date' => '2026-12-06', 'type' => 'closed', 'closed_shifts' => ['Tối|17:00|22:00']])->assertSessionHasErrors('closed_shifts');
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'closed'])->assertSessionHasErrors('closed_shifts');
    }

    public function test_open_on_a_normally_closed_day(): void
    {
        $this->assertFalse($this->open('2026-12-06 11:00', '2026-12-06 12:00'));

        $this->post($this->base, [
            'date' => '2026-12-06', 'type' => 'open', 'open_shifts' => ['Trưa|10:00|14:00'],
            'custom_enabled' => 1, 'custom_name' => 'Tiệc tối', 'custom_start' => '18:00', 'custom_end' => '23:30', 'reason' => 'Giáng sinh',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->open('2026-12-06 11:00', '2026-12-06 12:00'));
        $this->assertTrue($this->open('2026-12-06 22:00', '2026-12-06 23:30'));
        $this->assertFalse($this->open('2026-12-06 15:00', '2026-12-06 16:00'));
        $this->assertSame('Trưa 10:00 – 14:00, Tiệc tối 18:00 – 23:30', $this->day('2026-12-06')->periodsText());
    }

    public function test_open_day_replaces_regular_hours(): void
    {
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'open', 'custom_enabled' => 1, 'custom_name' => 'Cả ngày', 'custom_start' => '08:00', 'custom_end' => '23:00'])->assertSessionHasNoErrors();

        $this->assertTrue($this->open('2026-12-07 15:00', '2026-12-07 16:00'));
    }

    public function test_opening_needs_a_shift(): void
    {
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'open'])->assertSessionHasErrors('open_shifts');
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'open', 'open_shifts' => ['Không có|01:00|02:00']])->assertSessionHasErrors('open_shifts');
    }

    public function test_one_entry_per_date_and_edit_delete(): void
    {
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'closed', 'whole_day' => 1]);
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'closed', 'whole_day' => 1])->assertSessionHasErrors(['date' => __('special_days.date_taken')]);

        $day = $this->day('2026-12-07');
        $this->get("$this->base/{$day->id}/edit")->assertOk()->assertSee('data-closed-shifts', false)->assertDontSee('name="reason" value="" maxlength="255" required', false);

        $this->put("$this->base/{$day->id}", ['date' => '2026-12-07', 'type' => 'open', 'open_shifts' => ['Tối|17:00|22:00']])->assertSessionHasNoErrors();
        $this->assertSame(SpecialDayType::Open, $day->fresh()->type);

        $this->delete("$this->base/{$day->id}");
        $this->assertSame(0, SpecialDay::forRestaurant($this->restaurant)->count());
    }

    public function test_closing_a_day_with_bookings_warns(): void
    {
        $this->enableModules($this->restaurant, 'reservation');
        $this->actingInTenant($this->restaurant);
        $area = Area::forRestaurant($this->restaurant)->first();
        app(ReservationBooking::class)->create([
            'area_id' => $area->id, 'reserved_at' => '2026-12-07 19:00', 'party_size' => 2,
            'customer_name' => 'Chị Lan', 'customer_phone' => '0901111222',
        ], ReservationSource::Phone);

        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'closed', 'closed_shifts' => ['Trưa|10:00|14:00']])->assertSessionMissing('warning');
        $day = $this->day('2026-12-07');

        $this->put("$this->base/{$day->id}", ['date' => '2026-12-07', 'type' => 'closed', 'whole_day' => 1])
            ->assertSessionHas('warning', __('special_days.has_bookings', ['count' => 1, 'names' => 'Chị Lan 19:00']));
    }

    public function test_old_closed_dates_come_from_the_migration_and_are_logged(): void
    {
        $this->post($this->base, ['date' => '2026-12-07', 'type' => 'closed', 'whole_day' => 1]);

        $this->get("/{$this->restaurant->slug}/admin/activity")->assertSee('Thêm ngày đặc biệt 07/12/2026');
    }

    public function test_only_owner_and_only_own_restaurant(): void
    {
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Manager]);
        $this->actingAs($manager)->get($this->base)->assertForbidden();

        $other = Restaurant::factory()->create();
        $this->actingInTenant($other);
        $theirs = SpecialDay::create(['date' => '2026-12-07', 'type' => SpecialDayType::Closed, 'periods' => []]);

        $this->actingAs(User::factory()->owner($this->restaurant)->create())->get("$this->base/{$theirs->id}/edit")->assertNotFound();
    }
}
