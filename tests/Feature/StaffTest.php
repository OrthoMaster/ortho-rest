<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Position;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\UserDevice;
use App\Modules\ModuleManager;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private string $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'reservation', 'staff');
        $this->owner = User::factory()->owner($this->restaurant)->create(['email' => 'chu@quan.vn']);
        $this->admin = "/{$this->restaurant->slug}/admin";
        $this->actingAs($this->owner);
    }

    private function position(string $preset): Position
    {
        return Position::forRestaurant($this->restaurant)->where('preset', $preset)->sole();
    }

    public function test_enabling_creates_default_positions_with_permissions_of_enabled_modules(): void
    {
        $manager = $this->position('manager');
        $staff = $this->position('staff');

        $this->assertSame('Quản lý', $manager->name);
        $this->assertContains('reservation.settings', $manager->permissions);
        $this->assertNotContains('reservation.settings', $staff->permissions);
        $this->assertContains('reservation.create', $staff->permissions);
        $this->assertNotContains('takeaway.view', $staff->permissions);
        $this->assertNotContains('staff.manage', $manager->permissions);

        // Bật thêm module thì chức vụ mặc định nhận quyền cơ bản của module đó.
        $this->enableModules($this->restaurant, 'menu', 'takeaway');
        $this->assertContains('takeaway.view', $this->position('staff')->permissions);
        $this->assertContains('takeaway.settings', $this->position('manager')->permissions);

        // Bật lại không tạo thêm chức vụ.
        app(ModuleManager::class)->disable($this->restaurant, 'staff');
        $this->enableModules($this->restaurant, 'staff');
        $this->assertSame(2, Position::forRestaurant($this->restaurant)->count());
    }

    public function test_staff_logs_in_by_phone_or_email(): void
    {
        $staff = $this->member($this->restaurant, 'staff', ['phone' => '0901234567', 'email' => null]);
        $this->post('/logout');

        foreach (['0901234567', '090 123 4567', '+84 90 123 4567'] as $login) {
            $this->post("/{$this->restaurant->slug}/login", ['login' => $login, 'password' => 'password'])->assertRedirect("$this->admin");
            $this->assertAuthenticatedAs($staff);
            $this->post('/logout');
        }

        $this->post("/{$this->restaurant->slug}/login", ['login' => 'CHU@quan.vn', 'password' => 'password'])->assertRedirect($this->admin);
        $this->assertAuthenticatedAs($this->owner);
        $this->post('/logout');

        // Số điện thoại chỉ đúng trong quán của mình; trang super admin chỉ nhận email.
        $other = Restaurant::factory()->create();
        $this->post("/{$other->slug}/login", ['login' => '0901234567', 'password' => 'password'])->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->post('/login', ['login' => '0901234567', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->get("/{$this->restaurant->slug}/login")->assertSee(__('ui.login.email_or_phone'));
    }

    public function test_stopped_accounts_and_turned_off_module_keep_staff_out(): void
    {
        $staff = $this->member($this->restaurant, 'staff', ['phone' => '0901234567']);
        $this->post('/logout');

        $this->post("/{$this->restaurant->slug}/login", ['login' => '0901234567', 'password' => 'password']);
        $this->assertAuthenticatedAs($staff);

        // Tắt Nội bộ: nhân viên bị đăng xuất ở lần mở trang kế tiếp, chỉ chủ quán làm việc.
        app(ModuleManager::class)->disable($this->restaurant, 'staff');
        $this->get($this->admin)->assertRedirect("/{$this->restaurant->slug}/login")->assertSessionHasErrors(['login' => __('staff.module_off_login')]);
        $this->assertGuest();
        $this->post("/{$this->restaurant->slug}/login", ['login' => '0901234567', 'password' => 'password'])->assertSessionHasErrors(['login' => __('staff.module_off_login')]);
        $this->assertFalse($staff->fresh()->can('reservation.view'));
        $this->assertTrue($this->owner->fresh()->can('reservation.view'));

        $this->enableModules($this->restaurant, 'staff');
        $staff->forceFill(['is_active' => false])->save();
        $this->post("/{$this->restaurant->slug}/login", ['login' => '0901234567', 'password' => 'password'])->assertSessionHasErrors(['login' => __('staff.inactive_login')]);
        $this->assertGuest();
    }

    public function test_permissions_decide_pages_buttons_and_menu(): void
    {
        $staff = $this->member($this->restaurant, 'staff');

        $this->actingAs($staff)->get("$this->admin/reservations")->assertOk()->assertSee(__('reservation.add'));
        $this->get($this->admin)->assertDontSee('href="http://localhost'.$this->admin.'/staff"', false)
            ->assertDontSee('href="http://localhost'.$this->admin.'/restaurant"', false)
            ->assertSee('href="http://localhost'.$this->admin.'/customers"', false);
        $this->get("$this->admin/reservations/settings")->assertForbidden();
        $this->get("$this->admin/staff/accounts")->assertForbidden();

        // Bỏ quyền thêm đặt bàn khỏi chức vụ: nút và trang đều mất.
        $position = $this->position('staff');
        $position->update(['permissions' => array_values(array_diff($position->permissions, ['reservation.create']))]);
        $staff->forgetPermissions();
        $this->get("$this->admin/reservations")->assertOk()->assertDontSee(__('reservation.add'));
        $this->get("$this->admin/reservations/create")->assertForbidden();

        // Thêm chức vụ có quyền Nội bộ: thấy mục Nội bộ.
        $hr = Position::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Nhân sự', 'permissions' => ['staff.manage']]);
        $staff->positions()->attach($hr);
        $staff->forgetPermissions();
        $this->get($this->admin)->assertSee('href="http://localhost'.$this->admin.'/staff"', false);
        $this->get("$this->admin/staff")->assertRedirect("http://localhost$this->admin/staff/accounts");
        $this->get("$this->admin/staff/accounts")->assertOk();
        $this->get("$this->admin/staff/positions")->assertForbidden();
    }

    public function test_owner_creates_and_edits_staff_accounts(): void
    {
        $this->get("$this->admin/staff/accounts/create")->assertOk()->assertSee('Quản lý')->assertSee('data-password-toggle', false);

        $this->post("$this->admin/staff/accounts", ['name' => 'Lan', 'phone' => '', 'password' => 'short'])->assertSessionHasErrors(['phone', 'password']);

        $this->post("$this->admin/staff/accounts", [
            'name' => 'Lan', 'phone' => '090 222 3333', 'email' => '', 'password' => 'matkhau123',
            'positions' => [$this->position('staff')->id],
        ])->assertRedirect("http://localhost$this->admin/staff/accounts");

        $lan = User::where('phone', '0902223333')->sole();
        $this->assertSame(UserRole::Staff, $lan->role);
        $this->assertSame($this->restaurant->id, $lan->restaurant_id);
        $this->assertNull($lan->email);
        $this->assertSame(['Nhân viên'], $lan->positions->pluck('name')->all());

        // Trùng số trong quán thì không cho.
        $this->post("$this->admin/staff/accounts", ['name' => 'Lan 2', 'phone' => '0902223333', 'password' => 'matkhau123'])->assertSessionHasErrors('phone');

        // Đổi chức vụ và đặt mật khẩu mới: đăng xuất mọi thiết bị của Lan.
        UserDevice::create(['user_id' => $lan->id, 'token_hash' => hash('sha256', 'x'), 'name' => 'iPhone', 'last_seen_at' => now()]);
        $this->put("$this->admin/staff/accounts/{$lan->id}", [
            'name' => 'Lan', 'phone' => '0902223333', 'password' => 'matkhaumoi1', 'positions' => [$this->position('manager')->id],
        ])->assertSessionHasNoErrors();

        $lan->refresh();
        $this->assertSame(['Quản lý'], $lan->positions->pluck('name')->all());
        $this->assertSame(0, $lan->devices()->count());
        $this->assertTrue(password_verify('matkhaumoi1', $lan->password));

        $log = ActivityLog::where('area', 'staff')->latest('id')->first();
        $this->assertSame(__('staff.log_updated', ['name' => 'Lan']), $log->summary);
        $this->assertContains('Chức vụ: Nhân viên → Quản lý', $log->changes);
        $this->assertContains(__('staff.log_password'), $log->changes);

        // Ngừng làm việc rồi cho làm lại.
        $this->get("$this->admin/staff/accounts")->assertSee('Lan')->assertSee(__('staff.deactivate'));
        $this->post("$this->admin/staff/accounts/{$lan->id}/active")->assertSessionHasNoErrors();
        $this->assertFalse($lan->fresh()->is_active);
        $this->get("$this->admin/staff/accounts")->assertSee(__('staff.inactive'))->assertSee(__('staff.activate'));
        $this->post("$this->admin/staff/accounts/{$lan->id}/active");
        $this->assertTrue($lan->fresh()->is_active);
    }

    public function test_owner_and_other_restaurants_accounts_are_off_limits(): void
    {
        $this->get("$this->admin/staff/accounts/{$this->owner->id}/edit")->assertNotFound();
        $this->post("$this->admin/staff/accounts/{$this->owner->id}/active")->assertNotFound();

        $other = Restaurant::factory()->create();
        $stranger = $this->member($other, 'staff');
        $this->get("$this->admin/staff/accounts/{$stranger->id}/edit")->assertNotFound();

        // Người quản lý tài khoản không tự ngừng tài khoản của mình được.
        $hr = $this->member($this->restaurant, 'staff');
        $hr->positions()->attach(Position::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Nhân sự', 'permissions' => ['staff.manage']]));
        $hr->forgetPermissions();
        $this->actingAs($hr)->post("$this->admin/staff/accounts/{$hr->id}/active")->assertForbidden();
    }

    public function test_positions_are_created_edited_and_logged(): void
    {
        $this->get("$this->admin/staff/positions")->assertOk()->assertSee(__('staff.owner_all'))->assertSee('Quản lý')->assertSee('Nhân viên');

        // Quyền của module đang tắt không chọn được.
        $this->post("$this->admin/staff/positions", ['name' => 'Thu ngân', 'permissions' => ['takeaway.view']])->assertSessionHasErrors('permissions.0');
        $this->post("$this->admin/staff/positions", ['name' => 'Quản lý', 'permissions' => []])->assertSessionHasErrors('name');

        $this->assertSame('Thêm, sửa đặt bàn', Permissions::label('reservation.create'));
        $this->get("$this->admin/staff/positions/create")->assertSee(Permissions::label('reservation.create'))->assertDontSee(Permissions::label('takeaway.view'));
        $this->post("$this->admin/staff/positions", ['name' => 'Thu ngân', 'permissions' => ['reservation.view', 'customers.view']])->assertSessionHasNoErrors();

        $cashier = Position::forRestaurant($this->restaurant)->where('name', 'Thu ngân')->sole();
        $this->assertSame(['reservation.view', 'customers.view'], $cashier->permissions);

        // Quyền của module đang tắt được giữ khi sửa.
        $cashier->update(['permissions' => [...$cashier->permissions, 'takeaway.view']]);
        $this->put("$this->admin/staff/positions/{$cashier->id}", ['name' => 'Thu ngân', 'permissions' => ['reservation.view', 'reservation.status']])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['reservation.view', 'reservation.status', 'takeaway.view'], $cashier->fresh()->permissions);

        $log = ActivityLog::where('area', 'staff')->where('subject_id', $cashier->id)->latest('id')->first();
        $this->assertContains(__('activity.permissions_added', ['list' => Permissions::label('reservation.status')]), $log->changes);
        $this->assertContains(__('activity.permissions_removed', ['list' => Permissions::label('customers.view')]), $log->changes);

        $this->post("$this->admin/staff/positions/reorder", ['ids' => [$cashier->id, $this->position('manager')->id, $this->position('staff')->id]])->assertNoContent();
        $this->assertSame(['Thu ngân', 'Quản lý', 'Nhân viên'], Position::forRestaurant($this->restaurant)->orderBy('sort_order')->pluck('name')->all());

        $this->delete("$this->admin/staff/positions/{$cashier->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, Position::forRestaurant($this->restaurant)->where('name', 'Thu ngân')->count());
    }

    public function test_staff_module_off_hides_the_menu_and_pages(): void
    {
        app(ModuleManager::class)->disable($this->restaurant, 'staff');

        $this->get($this->admin)->assertDontSee('href="http://localhost'.$this->admin.'/staff"', false);
        $this->get("$this->admin/staff/accounts")->assertNotFound();
    }
}
