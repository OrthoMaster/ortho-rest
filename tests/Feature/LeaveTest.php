<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use App\Modules\Reservation\Models\Area;
use App\Modules\Schedule\LeaveBook;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\LeaveRequest;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private User $lan;

    private string $base;

    private Shift $lunch;

    private Shift $dinner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00'));
        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'staff', 'schedule');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->lan = $this->member($this->restaurant, 'staff', ['name' => 'Lan']);
        // Làm từ 15/02: đủ 8 tháng, 1,25 ngày mỗi tháng = 10 ngày phép.
        $this->lan->forceFill(['started_on' => '2026-02-15', 'paid_leave_per_month' => 1.25])->save();
        $this->base = "/{$this->restaurant->slug}/admin/schedule/leave";

        app(TenantContext::class)->set($this->restaurant);
        $this->lunch = Shift::create(['type' => ShiftType::Work, 'weekday' => 4, 'name' => 'Ca trưa', 'start_time' => '10:00', 'end_time' => '14:00']);
        $this->dinner = Shift::create(['type' => ShiftType::Work, 'weekday' => 4, 'name' => 'Ca tối', 'start_time' => '17:00', 'end_time' => '23:00']);
        app(TenantContext::class)->forget();
    }

    public function test_staff_requests_whole_days_and_shifts(): void
    {
        $this->actingAs($this->lan)->get($this->base)->assertOk()->assertSee(__('leave.balance_value', ['days' => '10']));
        $this->get("$this->base/create")->assertOk()->assertDontSee('name="user_id"', false);

        $this->post($this->base, ['starts_on' => '2026-11-16', 'ends_on' => '2026-11-18', 'whole_day' => 1, 'reason' => 'Về quê'])
            ->assertRedirect($this->base)->assertSessionHas('status', __('leave.sent'));
        // Thứ năm 12/11, nghỉ ca trưa: 4 giờ / 8 giờ = 0,5 ngày.
        $this->post($this->base, ['starts_on' => '2026-11-12', 'ends_on' => '2026-11-12', 'whole_day' => 0, 'shifts' => [$this->lunch->id]]);

        $leaves = LeaveRequest::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame([3.0, 0.5], $leaves->pluck('days')->all());
        $this->assertSame([$this->lunch->id], $leaves[1]->shift_ids);
        $this->assertSame($this->lan->id, $leaves[0]->created_by);

        // Ca tối cùng ngày vẫn xin được; trùng ngày đã xin thì không.
        $this->post($this->base, ['starts_on' => '2026-11-12', 'ends_on' => '2026-11-12', 'whole_day' => 0, 'shifts' => [$this->dinner->id]])->assertSessionHasNoErrors();
        $this->post($this->base, ['starts_on' => '2026-11-17', 'ends_on' => '2026-11-17', 'whole_day' => 1])->assertSessionHasErrors(['starts_on' => __('leave.overlap')]);

        // Không cho ngày đã qua, nhiều ngày theo ca, không chọn ca.
        $this->post($this->base, ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'whole_day' => 1])->assertSessionHasErrors('starts_on');
        $this->post($this->base, ['starts_on' => '2026-11-19', 'ends_on' => '2026-11-20', 'whole_day' => 0, 'shifts' => [$this->lunch->id]])->assertSessionHasErrors(['ends_on' => __('leave.range_one_day')]);
        $this->post($this->base, ['starts_on' => '2026-11-20', 'ends_on' => '2026-11-20', 'whole_day' => 0, 'shifts' => [$this->lunch->id]])->assertSessionHasErrors(['shifts' => __('leave.pick_shift')]); // ca trưa là của thứ năm, 20/11 là thứ sáu

        // Nhân viên không xin thay người khác, không duyệt được.
        $this->post($this->base, ['user_id' => $this->owner->id, 'starts_on' => '2026-11-25', 'ends_on' => '2026-11-25', 'whole_day' => 1])->assertSessionHasErrors('user_id');
        $this->post("$this->base/{$leaves[0]->id}/decide", ['action' => 'approve'])->assertForbidden();

        // Huỷ đơn của mình đang chờ.
        $this->post("$this->base/{$leaves[0]->id}/cancel")->assertSessionHas('status');
        $this->assertSame(LeaveStatus::Cancelled, $leaves[0]->fresh()->status);
    }

    public function test_approving_paid_leave_uses_balance_and_clears_schedule(): void
    {
        $this->actingAs($this->owner)->post($this->base, ['user_id' => $this->lan->id, 'starts_on' => '2026-11-12', 'ends_on' => '2026-11-13', 'whole_day' => 1]);
        $leave = LeaveRequest::withoutGlobalScopes()->sole();
        $this->assertSame($this->owner->id, $leave->created_by);

        app(TenantContext::class)->set($this->restaurant);
        $area = Area::create(['name' => 'Trong quán', 'code' => 'A', 'table_count' => 5, 'capacity' => 20]);
        Assignment::create(['work_date' => '2026-11-12', 'shift_id' => $this->lunch->id, 'area_id' => $area->id, 'user_id' => $this->lan->id]);
        Assignment::create(['work_date' => '2026-11-14', 'area_id' => $area->id, 'user_id' => $this->lan->id]);
        app(TenantContext::class)->forget();

        $this->get($this->base)->assertOk()->assertSee(__('leave.scheduled', ['name' => 'Lan', 'count' => 1]))->assertSee(__('leave.decide'));

        $this->post("$this->base/{$leave->id}/decide", ['action' => 'approve', 'is_paid' => 1, 'decision_note' => 'Ok'])->assertSessionHas('status');
        $leave->refresh();
        $this->assertSame(LeaveStatus::Approved, $leave->status);
        $this->assertTrue($leave->is_paid);
        $this->assertSame(['2026-11-14'], Assignment::withoutGlobalScopes()->get()->map(fn ($row) => $row->work_date->toDateString())->all());
        $this->assertSame(8.0, LeaveBook::balance($this->lan->fresh())['left']);

        // Không duyệt lại; huỷ đơn đã duyệt thì trả phép.
        $this->post("$this->base/{$leave->id}/decide", ['action' => 'reject'])->assertStatus(422);
        $this->post("$this->base/{$leave->id}/cancel")->assertSessionHas('status');
        $this->assertSame(10.0, LeaveBook::balance($this->lan->fresh())['left']);
    }

    public function test_unpaid_and_rejected_do_not_use_balance(): void
    {
        $make = fn () => LeaveRequest::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $this->lan->id, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-20', 'days' => 1, 'status' => LeaveStatus::Pending]);

        $first = $make();
        $this->actingAs($this->owner)->post("$this->base/{$first->id}/decide", ['action' => 'approve', 'is_paid' => 0]);
        $this->assertFalse($first->fresh()->is_paid);

        $second = $make();
        $this->post("$this->base/{$second->id}/decide", ['action' => 'reject', 'decision_note' => 'Thiếu người']);
        $this->assertSame(LeaveStatus::Rejected, $second->fresh()->status);
        $this->assertNull($second->fresh()->is_paid);

        $this->assertSame(10.0, LeaveBook::balance($this->lan->fresh())['left']);
        $this->get("$this->base?status=all")->assertSee('Thiếu người')->assertSee(__('leave.unpaid'));
    }

    public function test_balance_needs_contract_info(): void
    {
        $this->assertNull(LeaveBook::balance($this->owner));
        $this->actingAs($this->owner)->get($this->base)->assertSee(__('leave.balance_none'));
    }
}
