<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Models\ActivityLog;
use App\Models\Department;
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
        // Nhân viên: Đơn xin nghỉ chuyển sang Nghỉ phép (phép còn lại, đơn của mình); không có tab Đơn xin nghỉ.
        $this->actingAs($this->lan)->get($this->base)->assertRedirect("$this->base/mine");
        $this->get("$this->base/mine")->assertOk()->assertSee(__('leave.balance_value', ['days' => '10']))->assertDontSee(__('leave.mine_pending'))
            ->assertDontSee('/admin/schedule/leave"', false);
        $this->get("$this->base/create")->assertOk()->assertDontSee('name="user_id"', false);

        $this->post($this->base, ['starts_on' => '2026-11-16', 'ends_on' => '2026-11-18', 'whole_day' => 1, 'reason' => 'Về quê'])
            ->assertRedirect("$this->base/mine")->assertSessionHas('status', __('leave.sent'));
        // Có đơn chờ thì hiện trên cùng, trước Lịch sử phép; Các đơn đã xin là trang riêng.
        $this->get("$this->base/mine")->assertSeeInOrder([__('leave.mine_pending'), __('leave.history'), __('leave.history_monthly', ['month' => '02/2026'])]);
        $this->get("$this->base/mine?view=requests")->assertOk()->assertSee(__('leave.empty'));
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

    public function test_approver_can_approve_while_entering(): void
    {
        app(TenantContext::class)->set($this->restaurant);
        $area = Area::create(['name' => 'Trong quán', 'code' => 'A', 'table_count' => 5, 'capacity' => 20]);
        Assignment::create(['work_date' => '2026-11-12', 'shift_id' => $this->lunch->id, 'area_id' => $area->id, 'user_id' => $this->lan->id]);
        app(TenantContext::class)->forget();

        // Người duyệt: chọn người nghỉ có ô tìm, có nút Duyệt và Gửi đơn. Nhân viên chỉ thấy tên mình.
        $this->actingAs($this->owner)->get("$this->base/create")->assertOk()->assertSee('data-pick', false)
            ->assertSee('name="action" value="approve"', false)->assertSee('name="action" value="send"', false);
        $this->actingAs($this->lan)->get("$this->base/create")->assertSee('Lan')->assertDontSee('data-pick', false)->assertDontSee('value="approve"', false);

        $this->actingAs($this->owner)->post($this->base, ['user_id' => $this->lan->id, 'starts_on' => '2026-11-12', 'ends_on' => '2026-11-12', 'whole_day' => 1, 'is_paid' => 1, 'action' => 'approve'])
            ->assertSessionHas('status', __('leave.approved', ['name' => 'Lan']));
        $leave = LeaveRequest::withoutGlobalScopes()->sole();
        $this->assertSame(LeaveStatus::Approved, $leave->status);
        $this->assertTrue($leave->is_paid);
        $this->assertSame($this->owner->id, $leave->decided_by);
        $this->assertSame(0, Assignment::withoutGlobalScopes()->count());
        $this->assertSame(9.0, LeaveBook::balance($this->lan->fresh())['left']);

        // Gửi đơn thì vẫn chờ duyệt; nhân viên gửi action=approve cũng chỉ là đơn chờ.
        $this->post($this->base, ['user_id' => $this->lan->id, 'starts_on' => '2026-11-16', 'ends_on' => '2026-11-16', 'whole_day' => 1, 'action' => 'send']);
        $this->actingAs($this->lan)->post($this->base, ['starts_on' => '2026-11-18', 'ends_on' => '2026-11-18', 'whole_day' => 1, 'action' => 'approve']);
        $this->assertSame([LeaveStatus::Pending, LeaveStatus::Pending], LeaveRequest::withoutGlobalScopes()->where('id', '>', $leave->id)->orderBy('id')->pluck('status')->all());
    }

    public function test_calendar_counts_leave_by_shift_and_department(): void
    {
        $dept = Department::withoutGlobalScopes()->where('restaurant_id', $this->restaurant->id)->orderBy('sort_order')->first();
        $this->lan->forceFill(['department_id' => $dept->id])->save();
        $minh = $this->member($this->restaurant, 'staff', ['name' => 'Minh']);
        $minh->forceFill(['department_id' => $dept->id])->save();
        $make = fn (User $user, array $attributes) => LeaveRequest::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $user->id, 'days' => 1, ...$attributes]);
        // Thứ năm 12/11: Lan nghỉ cả ngày (đã duyệt), Minh xin nghỉ ca trưa (chờ duyệt).
        $make($this->lan, ['starts_on' => '2026-11-12', 'ends_on' => '2026-11-12', 'status' => LeaveStatus::Approved]);
        $make($minh, ['starts_on' => '2026-11-12', 'ends_on' => '2026-11-12', 'shift_ids' => [$this->lunch->id], 'status' => LeaveStatus::Pending]);

        $this->actingAs($this->owner)->get("$this->base?date=2026-11-10")->assertOk()
            ->assertSee(__('leave.calendar_title'))->assertSee($dept->name)
            ->assertSee('Ca trưa: '.__('leave.calendar_cell', ['approved' => 1, 'pending' => 1, 'people' => 2]), false)
            ->assertSee('Ca tối: '.__('leave.calendar_cell', ['approved' => 1, 'pending' => 0, 'people' => 2]), false)
            ->assertSee(__('leave.mine'));

        // Lọc theo một ngày (bấm cột trên lịch): chỉ còn đơn nghỉ vào ngày đó, cột được chọn tô nổi.
        $make($this->lan, ['starts_on' => '2026-11-16', 'ends_on' => '2026-11-16', 'status' => LeaveStatus::Pending]);
        $this->get("$this->base?day=2026-11-16")->assertOk()->assertSee('Thứ 2 16/11')->assertDontSee('Thứ 5 12/11')
            ->assertSee('is-selected', false)->assertSee('data-day-url', false);
        $this->get("$this->base?status=all&day=2026-11-12")->assertSee('Thứ 5 12/11')->assertDontSee('Thứ 2 16/11');
        $this->get("$this->base?day=2026-11-20")->assertSee(__('leave.empty_pending'));
        $this->get("$this->base?status=all&day=2026-11-20")->assertSee(__('leave.filter_day_empty'));
        // Người duyệt thấy tên nhân viên rõ ràng, ngày viết đủ "Thứ 2".
        $this->get("$this->base?day=2026-11-16")->assertSee('<strong>Lan</strong>', false);
    }

    public function test_approver_adjusts_balance_with_history(): void
    {
        // Lan: 1,25 ngày/tháng từ 15/02/2026, đến 10/11 tích được 10 ngày.
        $this->actingAs($this->owner)->get("$this->base/mine?user={$this->lan->id}")->assertOk()
            ->assertSee(__('leave.adjust'))->assertSee(__('leave.history_monthly', ['month' => '02/2026']));

        $this->post("$this->base/balance/{$this->lan->id}", ['left' => '', 'reason' => ''])->assertSessionHasErrorsIn('adjust', ['left', 'reason']);
        $this->post("$this->base/balance/{$this->lan->id}", ['left' => 8.5, 'reason' => 'Vào làm giữa tháng 1'])
            ->assertRedirect("$this->base/mine?user={$this->lan->id}")
            ->assertSessionHas('status', __('leave.adjusted', ['name' => 'Lan', 'days' => '8,5']));
        $this->assertSame(8.5, LeaveBook::balance($this->lan->fresh())['left']);
        $this->assertSame(__('leave.log_adjusted', ['name' => 'Lan', 'from' => '10', 'to' => '8,5', 'reason' => 'Vào làm giữa tháng 1']), ActivityLog::withoutGlobalScopes()->latest('id')->value('summary'));
        $this->post("$this->base/balance/{$this->lan->id}", ['left' => 8.5, 'reason' => 'x'])->assertSessionHas('status', __('leave.adjust_same'));

        // Nhân viên thấy lịch sử: ai chỉnh, từ bao nhiêu thành bao nhiêu, lý do; không có nút chỉnh, không xem người khác.
        $this->actingAs($this->lan)->get("$this->base/mine?user={$this->owner->id}")->assertOk()
            ->assertSee(__('leave.history_adjust', ['name' => $this->owner->name, 'from' => '10', 'to' => '8,5']))
            ->assertSee('Vào làm giữa tháng 1')->assertSee('-1,5')->assertDontSee(__('leave.adjust_save'))->assertDontSee('data-pick', false);
        $this->post("$this->base/balance/{$this->lan->id}", ['left' => 30, 'reason' => 'x'])->assertForbidden();
    }

    public function test_owner_is_not_in_leave_book(): void
    {
        // Chủ quán mở Nghỉ phép: xem phép của nhân viên đầu danh sách, chủ quán không có trong ô chọn.
        $this->actingAs($this->owner)->get("$this->base/mine")->assertOk()
            ->assertSee('value="'.$this->lan->id.'" data-label="Lan" checked', false)
            ->assertDontSee('data-label="'.$this->owner->name.'"', false);
        $this->get("$this->base/mine?user={$this->owner->id}")->assertNotFound();
        $this->post("$this->base/balance/{$this->owner->id}", ['left' => 5, 'reason' => 'x'])->assertStatus(422);
        $this->get("$this->base/create")->assertDontSee('data-label="'.$this->owner->name.'"', false);
        $this->post($this->base, ['starts_on' => '2026-11-12', 'ends_on' => '2026-11-12', 'whole_day' => 1])->assertSessionHasErrors('user_id');
    }

    public function test_leave_book_filters_by_date_range(): void
    {
        $make = fn (array $attributes) => LeaveRequest::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $this->lan->id, 'days' => 1, 'status' => LeaveStatus::Approved, 'is_paid' => true, 'decided_at' => now(), ...$attributes]);
        $make(['starts_on' => '2026-10-02', 'ends_on' => '2026-10-02']);
        $make(['starts_on' => '2026-09-05', 'ends_on' => '2026-09-05', 'is_paid' => false]);

        $this->actingAs($this->lan);
        // Một dòng: ngày · cả ngày · số ngày, nhãn có lương / không lương.
        $this->get("$this->base/mine?view=requests")->assertOk()->assertSee(__('leave.filter_any'))
            ->assertSeeInOrder(['Thứ 6 02/10', __('schedule.whole_day'), '1 ngày', __('leave.paid')])->assertSee(__('leave.unpaid'));
        $this->get("$this->base/mine?view=requests&from=2026-10-01&to=2026-10-31")->assertSee('Thứ 6 02/10')->assertDontSee('Thứ 7 05/09');
        $this->get("$this->base/mine?view=requests&from=2026-01-01&to=2026-01-31")->assertSee(__('leave.filter_empty'));
        // Thông tin số ngày phép: chỉ các dòng trong khoảng.
        $this->get("$this->base/mine?from=2026-03-01&to=2026-03-31")->assertSee(__('leave.history_monthly', ['month' => '02/2026']))
            ->assertDontSee(__('leave.history_monthly', ['month' => '03/2026']));
    }

    public function test_leave_requests_and_decisions_are_pushed(): void
    {
        $sent = $this->recordPushes();
        $this->pushDevice($this->owner);
        $this->pushDevice($this->lan);

        // Nhân viên xin nghỉ: báo chủ quán (người duyệt), không báo chính mình.
        $this->actingAs($this->lan)->post($this->base, ['starts_on' => '2026-11-16', 'ends_on' => '2026-11-16', 'whole_day' => 1, 'reason' => 'Về quê']);
        $this->assertSame([$this->owner->id], $sent[0]['users']);
        $this->assertSame(__('leave.push_new', ['name' => 'Lan']), $sent[0]['title']);
        $this->assertStringContainsString('Về quê', $sent[0]['body']);

        // Chủ quán duyệt: báo người xin nghỉ.
        $leave = LeaveRequest::withoutGlobalScopes()->sole();
        $this->actingAs($this->owner)->post("$this->base/{$leave->id}/decide", ['action' => 'approve', 'is_paid' => 1]);
        $this->assertSame([$this->lan->id], $sent[1]['users']);
        $this->assertSame(__('leave.push_approved'), $sent[1]['title']);
    }

    public function test_pending_leave_shows_red_badge_for_approver_only(): void
    {
        $dashboard = "/{$this->restaurant->slug}/admin";
        $this->actingAs($this->owner)->get($dashboard)->assertDontSee('nav-badge')->assertDontSee('nav-dot');

        LeaveRequest::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $this->lan->id, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-20', 'days' => 1, 'status' => LeaveStatus::Pending]);

        $this->get($dashboard)->assertSee(__('ui.nav.attention', ['count' => 1]))->assertSee('nav-dot');
        $this->get($this->base)->assertSee('subnav-count');
        // Nhân viên không duyệt đơn: không có chấm đỏ.
        $this->actingAs($this->lan)->get($dashboard)->assertDontSee('nav-badge')->assertDontSee('nav-dot');
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
        $minh = $this->member($this->restaurant, 'staff', ['name' => 'Minh']);
        $this->assertNull(LeaveBook::balance($minh));
        $this->actingAs($minh)->get("$this->base/mine")->assertSee(__('leave.balance_none'));
    }
}
