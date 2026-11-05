<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->base = "/{$this->restaurant->slug}/admin/restaurant/shifts";
        $this->actingAs(User::factory()->owner($this->restaurant)->create());
    }

    private function shift(array $attributes = []): Shift
    {
        return Shift::create([
            'restaurant_id' => $this->restaurant->id,
            'type' => ShiftType::Opening,
            'weekday' => 1,
            'name' => 'Trưa',
            'start_time' => '10:00',
            'end_time' => '14:00',
            ...$attributes,
        ]);
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return ['name' => 'Tối', 'start_time' => '17:00', 'end_time' => '22:00', 'weekdays' => [1], ...$overrides];
    }

    private function shiftCount(ShiftType $type): int
    {
        return Shift::forRestaurant($this->restaurant)->where('type', $type)->count();
    }

    public function test_one_shift_can_be_added_to_several_days(): void
    {
        $this->post("$this->base/opening", $this->form(['weekdays' => [1, 2, 3]]))->assertSessionHasNoErrors();

        $this->assertSame(3, $this->shiftCount(ShiftType::Opening));
        $this->assertSame(0, $this->shiftCount(ShiftType::Work));
    }

    public function test_a_day_can_have_several_shifts_and_the_two_types_stay_apart(): void
    {
        $this->shift();
        $this->shift(['type' => ShiftType::Work, 'name' => 'Ca sáng', 'start_time' => '08:00', 'end_time' => '15:00']);

        $this->post("$this->base/opening", $this->form())->assertSessionHasNoErrors();

        $this->get("$this->base/opening")->assertOk()->assertSeeInOrder(['Trưa', 'Tối'])->assertDontSee('Ca sáng');
        $this->get("$this->base/work")->assertOk()->assertSee('Ca sáng')->assertDontSee('Trưa');
    }

    public function test_opening_shifts_cannot_overlap_but_work_shifts_can(): void
    {
        $this->shift();
        $this->shift(['type' => ShiftType::Work]);
        $overlap = $this->form(['start_time' => '13:00', 'end_time' => '15:00']);

        $this->post("$this->base/opening", $overlap)->assertSessionHasErrors('end_time');
        $this->assertSame(1, $this->shiftCount(ShiftType::Opening));

        $this->post("$this->base/work", $overlap)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->shiftCount(ShiftType::Work));

        $this->post("$this->base/opening", $this->form(['start_time' => '14:00', 'end_time' => '15:00']))->assertSessionHasNoErrors();
    }

    public function test_overlap_on_one_day_saves_nothing_for_the_other_days(): void
    {
        $this->shift(['weekday' => 2]);

        $this->post("$this->base/opening", $this->form(['weekdays' => [1, 2], 'start_time' => '09:00', 'end_time' => '11:00']))->assertSessionHasErrors('end_time');

        $this->assertSame(1, $this->shiftCount(ShiftType::Opening));
    }

    public function test_edit_and_delete(): void
    {
        $shift = $this->shift();
        $this->shift(['name' => 'Tối', 'start_time' => '17:00', 'end_time' => '22:00']);

        $this->get("$this->base/{$shift->id}/edit")->assertOk()->assertDontSee('weekdays[]', false);

        $this->put("$this->base/{$shift->id}", $this->form(['name' => 'Sáng', 'start_time' => '09:00', 'end_time' => '13:00']))->assertSessionHasNoErrors();
        $this->assertSame('Sáng', $shift->fresh()->name);

        $this->put("$this->base/{$shift->id}", $this->form(['start_time' => '16:00', 'end_time' => '18:00']))->assertSessionHasErrors('end_time');

        $this->delete("$this->base/{$shift->id}");
        $this->assertNull(Shift::forRestaurant($this->restaurant)->find($shift->id));
    }

    public function test_invalid_input(): void
    {
        $this->post("$this->base/opening", $this->form(['name' => '']))->assertSessionHasErrors('name');
        $this->post("$this->base/opening", $this->form(['end_time' => '17:00']))->assertSessionHasErrors('end_time');
        $this->post("$this->base/opening", $this->form(['weekdays' => []]))->assertSessionHasErrors('weekdays');
        $this->post("$this->base/opening", $this->form(['weekdays' => [8]]))->assertSessionHasErrors('weekdays.0');
        $this->get("$this->base/khac")->assertNotFound();
    }

    public function test_shift_can_run_past_midnight(): void
    {
        $shift = $this->shift(['weekday' => 5, 'start_time' => '18:00', 'end_time' => '01:00']);

        $this->assertSame('18:00 – 01:00 '.__('shifts.next_day'), $shift->timeRange());

        // 04/12/2026 là thứ sáu.
        $this->assertTrue($this->restaurant->isOpenBetween(CarbonImmutable::parse('2026-12-04 23:00'), CarbonImmutable::parse('2026-12-05 00:30')));
        $this->assertTrue($this->restaurant->isOpenBetween(CarbonImmutable::parse('2026-12-05 00:10'), CarbonImmutable::parse('2026-12-05 00:50')));
        $this->assertFalse($this->restaurant->isOpenBetween(CarbonImmutable::parse('2026-12-05 00:30'), CarbonImmutable::parse('2026-12-05 01:30')));
    }

    public function test_open_only_inside_a_shift_of_that_weekday(): void
    {
        $this->assertTrue($this->restaurant->isOpenBetween(CarbonImmutable::parse('2026-12-07 03:00'), CarbonImmutable::parse('2026-12-07 04:00')));

        // 07/12/2026 là thứ hai.
        $this->shift();
        $this->shift(['name' => 'Tối', 'start_time' => '17:00', 'end_time' => '22:00']);

        $open = fn (string $from, string $to) => $this->restaurant->isOpenBetween(CarbonImmutable::parse("2026-12-07 $from"), CarbonImmutable::parse("2026-12-07 $to"));

        $this->assertTrue($open('11:00', '12:30'));
        $this->assertTrue($open('20:30', '22:00'));
        $this->assertFalse($open('15:00', '16:00'));
        $this->assertFalse($open('13:30', '15:00'));
        $this->assertFalse($this->restaurant->isOpenBetween(CarbonImmutable::parse('2026-12-08 11:00'), CarbonImmutable::parse('2026-12-08 12:00')));
    }

    public function test_only_the_owner_can_manage_shifts(): void
    {
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Manager]);

        $this->actingAs($manager)->get("$this->base/opening")->assertForbidden();
        $this->post("$this->base/opening", $this->form())->assertForbidden();
    }

    public function test_shift_of_another_restaurant_is_not_reachable(): void
    {
        $other = Restaurant::factory()->create();
        $shift = Shift::create(['restaurant_id' => $other->id, 'type' => ShiftType::Opening, 'weekday' => 1, 'name' => 'Khác', 'start_time' => '10:00', 'end_time' => '12:00']);

        $this->get("$this->base/{$shift->id}/edit")->assertNotFound();
        $this->put("$this->base/{$shift->id}", $this->form())->assertNotFound();
        $this->delete("$this->base/{$shift->id}")->assertNotFound();
    }
}
