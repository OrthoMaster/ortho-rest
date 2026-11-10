<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Position;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\Models\Station;
use App\Modules\Reservation\Models\Area;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\DefaultSlot;
use App\Modules\Schedule\Models\LeaveRequest;
use App\Modules\Schedule\Models\Task;
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

    private Department $kitchenDept;

    private Department $serviceDept;

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
        [$this->serviceDept, $this->kitchenDept] = Department::orderBy('sort_order')->get()->all();
        $this->kitchen->update(['department_ids' => [$this->kitchenDept->id]]);
        $this->lunch = Shift::create(['type' => ShiftType::Work, 'weekday' => 2, 'name' => 'Ca trưa', 'start_time' => '10:00', 'end_time' => '14:00']);
        $this->dinner = Shift::create(['type' => ShiftType::Work, 'weekday' => 2, 'name' => 'Ca tối', 'start_time' => '17:00', 'end_time' => '22:30']);
        app(TenantContext::class)->forget();
    }

    private function staff(string $name, ?Department $department = null, array $attributes = []): User
    {
        $user = $this->member($this->restaurant, 'staff', ['name' => $name]);
        $user->forceFill(['department_id' => $department?->id, ...$attributes])->save();

        return $user;
    }

    private function place(User $user, string $date, string $slot, string $lane)
    {
        return $this->postJson("$this->base/assignments", ['user' => $user->id, 'date' => $date, 'slot' => $slot, 'lane' => $lane]);
    }

    public function test_staff_module_creates_departments(): void
    {
        $this->assertSame(['Phục vụ', 'Bếp'], [$this->serviceDept->name, $this->kitchenDept->name]);
    }

    public function test_dropping_people_into_cells_saves_and_counts_hours(): void
    {
        $lan = $this->staff('Lan', $this->kitchenDept, ['contract_hours' => 40, 'contract_period' => 'week']);

        $this->get($this->base)->assertRedirect("$this->base/week");
        $this->get("$this->base/assign?date=2026-11-10")->assertOk()
            ->assertSee('data-planner', false)->assertSee('Lan')->assertSee('Ca trưa')
            ->assertSee(__('schedule.stat_contract_week', ['shifts' => 0, 'used' => 0, 'limit' => 40]));

        $first = $this->place($lan, '2026-11-10', (string) $this->lunch->id, 's'.$this->kitchen->id)->assertOk()
            ->assertJsonPath('warnings', [])
            ->assertJsonPath("stats.{$lan->id}.label", __('schedule.stat_contract_week', ['shifts' => 1, 'used' => 4, 'limit' => 40]));

        // Cùng ô thì lỗi; hàng khác cùng ca thì nhắc; khu của bộ phận khác thì nhắc.
        $this->place($lan, '2026-11-10', (string) $this->lunch->id, 's'.$this->kitchen->id)->assertUnprocessable()
            ->assertJsonPath('message', __('schedule.already_here', ['name' => 'Lan']));
        $this->area->update(['department_ids' => [$this->serviceDept->id]]);
        $this->place($lan, '2026-11-10', (string) $this->lunch->id, 'a'.$this->area->id)->assertOk()
            ->assertJsonPath('warnings', [
                __('schedule.also_in', ['name' => 'Lan', 'lane' => 'Bếp', 'slot' => 'Ca trưa']),
                __('schedule.other_department', ['name' => 'Lan', 'mine' => 'Bếp', 'area' => 'Phục vụ']),
            ])
            // Hai hàng cùng một ca chỉ tính một ca.
            ->assertJsonPath("stats.{$lan->id}.label", __('schedule.stat_contract_week', ['shifts' => 1, 'used' => 4, 'limit' => 40]));

        // Kéo sang ca tối: 5,5 giờ.
        $id = $first->json('id');
        $this->patchJson("$this->base/assignments/$id", ['date' => '2026-11-10', 'slot' => (string) $this->dinner->id, 'lane' => 's'.$this->kitchen->id])->assertOk()
            ->assertJsonPath("stats.{$lan->id}.label", __('schedule.stat_contract_week', ['shifts' => 2, 'used' => '9,5', 'limit' => 40]));
        $this->assertSame($this->dinner->id, Assignment::withoutGlobalScopes()->find($id)->shift_id);

        // Ca không thuộc ngày đó, hàng không có: lỗi.
        $this->place($lan, '2026-11-11', (string) $this->lunch->id, 's'.$this->kitchen->id)->assertUnprocessable();
        $this->place($lan, '2026-11-10', (string) $this->lunch->id, 's999')->assertUnprocessable();

        $this->deleteJson("$this->base/assignments/$id")->assertOk()
            ->assertJsonPath("stats.{$lan->id}.label", __('schedule.stat_contract_week', ['shifts' => 1, 'used' => 4, 'limit' => 40]));
        $this->assertSame(1, Assignment::withoutGlobalScopes()->count());
    }

    public function test_task_lanes_and_whole_day(): void
    {
        $lan = $this->staff('Lan', $this->serviceDept);
        app(TenantContext::class)->set($this->restaurant);
        $cashier = Task::create(['name' => 'Đứng thanh toán', 'area_id' => $this->area->id]);
        $cleaning = Task::create(['name' => 'Dọn vệ sinh cuối buổi']);
        app(TenantContext::class)->forget();

        $this->get("$this->base/week?date=2026-11-10")->assertSee('Đứng thanh toán')->assertSee(__('schedule.general_tasks'))->assertSee('Dọn vệ sinh cuối buổi');

        $this->place($lan, '2026-11-10', (string) $this->dinner->id, "a{$this->area->id}-t{$cashier->id}")->assertOk();
        $this->place($lan, '2026-11-11', 'day', "g-t{$cleaning->id}")->assertOk(); // thứ tư chưa khai ca: cả ngày

        $rows = Assignment::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame([$cashier->id, $cleaning->id], $rows->pluck('schedule_task_id')->all());
        $this->assertNull($rows[1]->shift_id);
        $this->assertNull($rows[1]->area_id);

        // Lịch tuần lọc theo một người: chỉ còn hàng người đó làm, kèm tổng tuần.
        $this->get("$this->base/week?people[]={$lan->id}&date=2026-11-10")->assertOk()
            ->assertDontSee('data-planner', false)
            ->assertSee('Đứng thanh toán')->assertSee('Dọn vệ sinh cuối buổi')
            ->assertSee(__('schedule.person_total', ['shifts' => 2, 'hours' => '13,5']));
    }

    public function test_leave_blocks_or_warns_when_scheduling(): void
    {
        $lan = $this->staff('Lan', $this->kitchenDept);
        $make = fn (array $attributes) => LeaveRequest::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $lan->id, 'days' => 1, ...$attributes]);

        $make(['starts_on' => '2026-11-10', 'ends_on' => '2026-11-10', 'shift_ids' => [$this->lunch->id], 'status' => LeaveStatus::Approved]);
        $make(['starts_on' => '2026-11-12', 'ends_on' => '2026-11-13', 'status' => LeaveStatus::Pending]);

        $this->place($lan, '2026-11-10', (string) $this->lunch->id, 's'.$this->kitchen->id)->assertUnprocessable()
            ->assertJsonPath('message', __('schedule.leave_approved', ['name' => 'Lan', 'slot' => 'ca trưa', 'date' => '10/11']));
        $this->place($lan, '2026-11-10', (string) $this->dinner->id, 's'.$this->kitchen->id)->assertOk()->assertJsonPath('warnings', []);
        $this->place($lan, '2026-11-13', 'day', 's'.$this->kitchen->id)->assertOk()
            ->assertJsonPath('warnings', [__('schedule.leave_pending', ['name' => 'Lan', 'date' => '13/11'])]);

        // Phân công nhận danh sách đơn nghỉ để tô ô khi kéo và gắn chấm cảnh báo; bảng nhân viên không còn pill Nghỉ.
        $this->get("$this->base/assign?date=2026-11-10")->assertSee('"approved":true', false)->assertSee('"approved":false', false)
            ->assertSee('data-pending-label', false)->assertDontSee('leave-dot', false);
    }

    public function test_viewers_without_permission(): void
    {
        $lan = $this->staff('Lan');
        app(TenantContext::class)->set($this->restaurant);
        Assignment::create(['work_date' => '2026-11-10', 'shift_id' => $this->lunch->id, 'dinein_station_id' => $this->kitchen->id, 'user_id' => $lan->id]);
        app(TenantContext::class)->forget();

        // Nhân viên xem được lịch tuần nhưng không phân công; có toggle chỉ xem lịch của mình.
        $this->actingAs($lan)->get("$this->base/week?date=2026-11-10")->assertOk()->assertDontSee('data-planner', false)->assertSee('Lan')
            ->assertSee(__('schedule.only_me'))->assertDontSee(__('schedule.assign'));
        $this->get("$this->base/assign")->assertForbidden();
        $this->place($lan, '2026-11-10', (string) $this->dinner->id, 's'.$this->kitchen->id)->assertForbidden();

        // Bỏ quyền xem lịch mọi người: chỉ thấy lịch của mình.
        app(TenantContext::class)->set($this->restaurant);
        foreach (Position::all() as $position) {
            $position->update(['permissions' => array_values(array_diff($position->permissions, ['schedule.view']))]);
        }
        app(TenantContext::class)->forget();
        $minh = $this->staff('Minh');

        $this->actingAs($minh)->get($this->base)->assertRedirect("$this->base/week");
        // Lọc người khác cũng bị bỏ qua: chỉ lịch của mình (tuần này chưa có gì).
        $this->get("$this->base/week?people[]={$lan->id}&date=2026-11-10")->assertOk()
            ->assertSee(__('schedule.nothing_this_week'))->assertDontSee(__('schedule.only_me'));
    }

    public function test_copy_previous_week_keeps_tasks(): void
    {
        $lan = $this->staff('Lan');
        app(TenantContext::class)->set($this->restaurant);
        $task = Task::create(['name' => 'Kiểm kho']);
        Assignment::create(['work_date' => '2026-11-03', 'shift_id' => $this->lunch->id, 'dinein_station_id' => $this->kitchen->id, 'user_id' => $lan->id]);
        Assignment::create(['work_date' => '2026-11-05', 'schedule_task_id' => $task->id, 'user_id' => $lan->id]);
        Assignment::create(['work_date' => '2026-11-11', 'area_id' => $this->area->id, 'user_id' => $this->owner->id]);
        app(TenantContext::class)->forget();

        $this->post("$this->base/week/copy", ['date' => '2026-11-12'])
            ->assertRedirect("$this->base/assign?date=2026-11-09")
            ->assertSessionHas('status', trans_choice('schedule.copied', 2, ['count' => 2]));

        $rows = Assignment::withoutGlobalScopes()->whereDate('work_date', '>=', '2026-11-09')->orderBy('work_date')->get();
        $this->assertSame(['2026-11-10', '2026-11-12'], $rows->map(fn ($row) => $row->work_date->toDateString())->all());
        $this->assertSame($task->id, $rows[1]->schedule_task_id);
        $this->assertTrue(ActivityLog::where('area', 'schedule')->exists());
    }

    public function test_default_schedule_fills_week_and_flags_short_cells(): void
    {
        $lan = $this->staff('Lan', $this->kitchenDept);
        $minh = $this->staff('Minh', $this->kitchenDept);
        $kitchen = 's'.$this->kitchen->id;

        $this->get("$this->base/defaults")->assertOk()->assertSee('data-mode="defaults"', false)->assertSee('Ca trưa');

        // Thứ ba ca trưa ở bếp: cần 3 người, mặc định Lan và Minh.
        $this->putJson("$this->base/defaults", ['weekday' => 2, 'slot' => (string) $this->lunch->id, 'lane' => $kitchen, 'needed' => 3, 'users' => [$lan->id, $minh->id]])
            ->assertOk()->assertJsonPath("stats.{$lan->id}.label", __('schedule.stat_week', ['shifts' => 1, 'hours' => '4']));
        $this->putJson("$this->base/defaults", ['weekday' => 2, 'slot' => '999', 'lane' => $kitchen, 'users' => []])->assertUnprocessable();

        // Minh đã được duyệt nghỉ thứ ba: bỏ qua; Lan đã có trong ô ở tuần sau thì không thêm lại.
        LeaveRequest::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $minh->id, 'days' => 1, 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-10', 'status' => LeaveStatus::Approved]);
        $this->post("$this->base/week/defaults", ['date' => '2026-11-10'])
            ->assertRedirect("$this->base/assign?date=2026-11-09")
            ->assertSessionHas('status', trans_choice('schedule.defaults_applied', 1, ['count' => 1]).' '.__('schedule.defaults_skipped', ['count' => 1]));
        $this->post("$this->base/week/defaults", ['date' => '2026-11-10'])
            ->assertSessionHas('status', trans_choice('schedule.defaults_applied', 0, ['count' => 0]).' '.__('schedule.defaults_skipped', ['count' => 1]));
        $this->assertSame([$lan->id], Assignment::withoutGlobalScopes()->pluck('user_id')->all());

        // Ô có 1/3 người: tô vàng ở Phân công.
        $this->get("$this->base/assign?date=2026-11-10")->assertSee('1/3')->assertSee('is-short', false);

        // Bỏ hết người và số cần thì ô mặc định bị xoá.
        $this->putJson("$this->base/defaults", ['weekday' => 2, 'slot' => (string) $this->lunch->id, 'lane' => $kitchen, 'needed' => null, 'users' => []])->assertOk();
        $this->assertSame(0, DefaultSlot::withoutGlobalScopes()->count());

        $this->actingAs($lan)->get("$this->base/defaults")->assertForbidden();
    }

    public function test_closed_weekdays_cannot_be_scheduled(): void
    {
        $lan = $this->staff('Lan', $this->kitchenDept);
        $kitchen = 's'.$this->kitchen->id;
        // Quán chỉ mở thứ ba: các thứ khác là ngày nghỉ.
        app(TenantContext::class)->set($this->restaurant);
        Shift::create(['type' => ShiftType::Opening, 'weekday' => 2, 'name' => 'Trưa', 'start_time' => '11:00', 'end_time' => '14:00']);
        app(TenantContext::class)->forget();

        $this->get("$this->base/defaults")->assertOk()->assertSee(__('schedule.closed_day'));
        $this->putJson("$this->base/defaults", ['weekday' => 3, 'slot' => 'day', 'lane' => $kitchen, 'users' => [$lan->id]])->assertUnprocessable();
        $this->place($lan, '2026-11-11', 'day', $kitchen)->assertUnprocessable();
        $this->place($lan, '2026-11-10', (string) $this->lunch->id, $kitchen)->assertOk();

        // Ca của thứ ba nằm thành dòng riêng, mã khu ở dòng trên tên khu.
        $this->get("$this->base/week?date=2026-11-10")->assertSee('slot-tone-2', false)->assertSee('<span class="target-name">', false);
    }

    public function test_owner_can_be_left_out_of_the_roster(): void
    {
        $owner = 'data-drag-user="'.$this->owner->id.'"';
        $this->get("$this->base/assign?date=2026-11-10")->assertSee($owner, false);
        $this->place($this->owner, '2026-11-10', (string) $this->lunch->id, 's'.$this->kitchen->id)->assertOk();

        $this->put("$this->base/settings", ['workday_hours' => 8, 'include_owner' => '0'])->assertSessionHas('status');
        $this->get("$this->base/settings")->assertSee(__('schedule.include_owner'));
        $this->get("$this->base/assign?date=2026-11-10")->assertDontSee($owner, false);
        $this->place($this->owner, '2026-11-11', 'day', 's'.$this->kitchen->id)->assertUnprocessable();

        $this->put("$this->base/settings", ['workday_hours' => 8, 'include_owner' => '1']);
        $this->get("$this->base/assign?date=2026-11-10")->assertSee($owner, false);
    }

    public function test_settings_tasks_and_departments_of_areas(): void
    {
        $this->get("$this->base/settings")->assertOk()->assertSee(__('schedule.workday_hours'))->assertSee('Bếp');

        $this->put("$this->base/settings", ['workday_hours' => 7.5, 'departments' => ['a'.$this->area->id => [$this->serviceDept->id, $this->kitchenDept->id]]])
            ->assertSessionHas('status');
        // Một khu có thể thuộc nhiều bộ phận; khu không gửi lên thì không thuộc bộ phận nào.
        $this->assertSame([$this->serviceDept->id, $this->kitchenDept->id], $this->area->fresh()->department_ids);
        $this->assertSame([], $this->kitchen->fresh()->department_ids ?? []);

        $this->post("$this->base/tasks", ['name' => 'Tiếp thực', 'target' => 'a'.$this->area->id])->assertSessionHas('status');
        $this->post("$this->base/tasks", ['name' => 'Kiểm kho', 'target' => 'g'])->assertSessionHas('status');
        $this->post("$this->base/tasks", ['name' => '', 'target' => 'g'])->assertSessionHasErrorsIn('task-new', 'name');
        $this->post("$this->base/tasks", ['name' => 'Lạ', 'target' => 's999'])->assertUnprocessable();

        $tasks = Task::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame(['a'.$this->area->id, 'g'], $tasks->map->targetKey()->all());

        $this->put("$this->base/tasks/{$tasks[0]->id}", ['name' => 'Bưng bê'])->assertSessionHas('status');
        $this->assertSame('Bưng bê', $tasks[0]->fresh()->name);
        $this->delete("$this->base/tasks/{$tasks[1]->id}")->assertSessionHas('status');
        $this->assertSame(1, Task::withoutGlobalScopes()->count());

        // Ngày công 7,5 giờ: ngày chưa khai ca tính 7,5 giờ.
        $lan = $this->staff('Lan');
        $this->place($lan, '2026-11-11', 'day', 'a'.$this->area->id)->assertJsonPath("stats.{$lan->id}.label", __('schedule.stat_week', ['shifts' => 1, 'hours' => '7,5']));

        $this->actingAs($lan)->get("$this->base/settings")->assertForbidden();
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
