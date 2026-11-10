<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Models\ActivityLog;
use App\Models\Position;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\Models\Station;
use App\Modules\Reservation\Models\Area;
use App\Modules\Schedule\Models\Assignment;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private string $base;

    private Station $kitchen;

    private Area $area;

    private Shift $lunch;

    private Shift $dinner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00')); // thứ ba
        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'menu', 'dinein', 'staff', 'schedule');
        $this->owner = User::factory()->owner($this->restaurant)->create(['name' => 'Chủ quán']);
        $this->base = "/{$this->restaurant->slug}/admin/schedule";
        $this->actingAs($this->owner);

        app(TenantContext::class)->set($this->restaurant);
        $this->kitchen = Station::orderBy('sort_order')->first();
        $this->area = Area::sole();
        $this->lunch = Shift::create(['type' => ShiftType::Work, 'weekday' => 2, 'name' => 'Ca trưa', 'start_time' => '10:00', 'end_time' => '14:00']);
        $this->dinner = Shift::create(['type' => ShiftType::Work, 'weekday' => 2, 'name' => 'Ca tối', 'start_time' => '17:00', 'end_time' => '22:30']);
        app(TenantContext::class)->forget();
    }

    private function assign(string $date, ?Shift $shift, string $target, User $user): void
    {
        Assignment::create([
            'restaurant_id' => $this->restaurant->id,
            'work_date' => $date,
            'shift_id' => $shift?->id,
            'dinein_station_id' => $target === 'kitchen' ? $this->kitchen->id : null,
            'area_id' => $target === 'area' ? $this->area->id : null,
            'user_id' => $user->id,
        ]);
    }

    public function test_day_view_saves_people_per_shift_and_area(): void
    {
        $lan = $this->member($this->restaurant, 'staff', ['name' => 'Lan']);
        $minh = $this->member($this->restaurant, 'staff', ['name' => 'Minh']);

        $this->get($this->base)->assertRedirect("$this->base/week");
        $this->get("$this->base/day?date=2026-11-10")->assertOk()
            ->assertSee('Ca trưa')->assertSee('Ca tối')->assertSee('Bếp')->assertSee('A · '.$this->area->name)
            ->assertDontSee(__('schedule.no_shifts'));

        $this->put("$this->base/day", ['date' => '2026-11-10', 'slots' => [
            $this->lunch->id => ['s'.$this->kitchen->id => [$lan->id], 'a'.$this->area->id => [$minh->id, $lan->id]],
            $this->dinner->id => ['s'.$this->kitchen->id => [$minh->id]],
            'day' => ['s'.$this->kitchen->id => [$minh->id]], // ngày có ca thì bỏ qua "cả ngày"
            999 => ['s'.$this->kitchen->id => [$minh->id]],   // ca không thuộc ngày này
        ]])->assertRedirect("$this->base/day?date=2026-11-10")->assertSessionHas('status', __('schedule.saved'));

        $this->assertSame(4, Assignment::withoutGlobalScopes()->count());
        $this->assertTrue(ActivityLog::where('area', 'schedule')->exists());

        // Lan ở hai khu trong ca trưa thì nhắc.
        $this->get("$this->base/day?date=2026-11-10")->assertSee(__('schedule.conflict', ['name' => 'Lan', 'slot' => 'Ca trưa']));

        // Người ngoài quán không xếp được.
        $stranger = User::factory()->owner(Restaurant::factory()->create())->create();
        $this->put("$this->base/day", ['date' => '2026-11-10', 'slots' => [$this->lunch->id => ['s'.$this->kitchen->id => [$stranger->id]]]])
            ->assertSessionHasErrors();

        // Lưu lại thay toàn bộ ngày đó.
        $this->put("$this->base/day", ['date' => '2026-11-10', 'slots' => []]);
        $this->assertSame(0, Assignment::withoutGlobalScopes()->count());
    }

    public function test_day_without_shifts_is_whole_day(): void
    {
        $lan = $this->member($this->restaurant, 'staff', ['name' => 'Lan']);

        $this->get("$this->base/day?date=2026-11-11")->assertSee(__('schedule.no_shifts'))->assertSee(__('schedule.whole_day'));
        $this->put("$this->base/day", ['date' => '2026-11-11', 'slots' => ['day' => ['a'.$this->area->id => [$lan->id]]]]);
        $this->assertNull(Assignment::withoutGlobalScopes()->sole()->shift_id);
    }

    public function test_week_and_person_views(): void
    {
        $lan = $this->member($this->restaurant, 'staff', ['name' => 'Lan']);
        $this->assign('2026-11-10', $this->lunch, 'kitchen', $lan);
        $this->assign('2026-11-10', $this->dinner, 'area', $lan);
        $this->assign('2026-11-12', null, 'area', $this->owner);

        $this->get("$this->base/week?date=2026-11-12")->assertOk()
            ->assertSee(__('schedule.week_range', ['from' => '09/11', 'to' => '15/11']))
            ->assertSee('Lan')->assertSee('Chủ quán')->assertSee('Ca trưa');
        $this->get("$this->base/week?date=2026-11-17")->assertDontSee('Lan');

        // 4 giờ ca trưa + 5,5 giờ ca tối.
        $this->get("$this->base/person?user={$lan->id}&date=2026-11-10")->assertOk()
            ->assertSee(__('schedule.person_total', ['shifts' => 2, 'hours' => '9,5']))
            ->assertSee($this->area->name)->assertSee('Bếp');

        // Nhân viên không có quyền xem lịch mọi người chỉ thấy lịch của mình.
        $minh = $this->member($this->restaurant, 'staff', ['name' => 'Minh']);
        app(TenantContext::class)->set($this->restaurant);
        foreach (Position::all() as $position) {
            $position->update(['permissions' => array_values(array_diff($position->permissions, ['schedule.view']))]);
        }
        app(TenantContext::class)->forget();

        $this->actingAs($minh)->get($this->base)->assertRedirect("$this->base/person");
        $this->get("$this->base/week")->assertForbidden();
        $this->get("$this->base/person?user={$lan->id}&date=2026-11-10")->assertOk()
            ->assertSee(__('schedule.person_total', ['shifts' => 0, 'hours' => '0']))->assertDontSee('Ca trưa');
        $this->put("$this->base/day", ['date' => '2026-11-10'])->assertForbidden();
    }

    public function test_copy_previous_week(): void
    {
        $lan = $this->member($this->restaurant, 'staff', ['name' => 'Lan']);
        $this->assign('2026-11-03', $this->lunch, 'kitchen', $lan);
        $this->assign('2026-11-05', null, 'area', $lan);
        $this->assign('2026-11-11', null, 'kitchen', $this->owner); // tuần này, sẽ bị thay

        $this->post("$this->base/week/copy", ['date' => '2026-11-12'])
            ->assertRedirect("$this->base/week?date=2026-11-09")
            ->assertSessionHas('status', trans_choice('schedule.copied', 2, ['count' => 2]));

        $dates = Assignment::withoutGlobalScopes()->whereDate('work_date', '>=', '2026-11-09')->orderBy('work_date')->get();
        $this->assertSame(['2026-11-10', '2026-11-12'], $dates->map(fn ($row) => $row->work_date->toDateString())->all());
        $this->assertSame($this->lunch->id, $dates[0]->shift_id);
    }

    public function test_module_needs_staff_and_shows_in_nav(): void
    {
        $this->get("/{$this->restaurant->slug}/admin")->assertSee(__('modules.schedule'));

        $plain = Restaurant::factory()->create();
        $this->enableModules($plain, 'menu');
        $this->actingAs(User::factory()->owner($plain)->create())
            ->get("/{$plain->slug}/admin/schedule/week")->assertNotFound();
    }
}
