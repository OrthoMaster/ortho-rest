<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\ModuleManager;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Thu ngân: phục vụ đóng bàn, phiếu chờ thu ở quầy; đơn mang đi cũng vậy; đối soát với máy tính tiền. */
class CashierTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private MenuItem $tra;

    private string $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00'));
        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'menu', 'dinein', 'takeaway', 'cashier');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->admin = "/{$this->restaurant->slug}/admin";
        $category = MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Nước']);
        $this->tra = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Trà đá',
            'price_dine_in' => 10000, 'price_takeaway' => 8000]);
        $this->actingAs($this->owner);
    }

    private function table(string $label, int $quantity): DiningTable
    {
        $this->post("$this->admin/dinein/tables/$label/order", ['lines' => [['item' => $this->tra->id, 'quantity' => $quantity]]]);

        return DiningTable::withoutGlobalScopes()->where('label', $label)->whereNull('closed_at')->sole();
    }

    private function takeaway(int $quantity): TakeawayOrder
    {
        $this->post("$this->admin/takeaway", ['customer_name' => 'Chị Lan', 'customer_phone' => '0987654321', 'pickup_date' => '2026-11-09',
            'pickup_time' => '13:00', 'lines' => [['item' => $this->tra->id, 'quantity' => $quantity]]])->assertSessionHasNoErrors();

        return TakeawayOrder::withoutGlobalScopes()->latest('id')->first();
    }

    public function test_closed_tables_and_delivered_orders_wait_at_the_counter_until_paid(): void
    {
        // Phục vụ đóng bàn: bàn trống ngay, phiếu chưa thu, chuyển sang quầy.
        $first = $this->table('A1', 3);
        $sent = $this->recordPushes();
        $this->pushDevice($manager = $this->member($this->restaurant, 'manager'));
        $this->post("$this->admin/dinein/tables/{$first->id}/close")->assertSessionHas('status', __('dinein.closed_to_cashier', ['table' => 'A1']));
        // Báo người thu tiền.
        $this->assertSame([$manager->id], $sent[0]['users']);
        $this->assertSame(__('cashier.push_pending', ['table' => 'A1']), $sent[0]['title']);
        $this->assertNull($first->fresh()->paid_at);
        $this->assertNotNull($first->fresh()->closed_at);

        // Bàn đang phục vụ cũng thấy ở quầy (khách ra quầy trước).
        $second = $this->table('A2', 1);

        // Đơn mang đi đã giao khách: chờ thu ở quầy.
        $order = $this->takeaway(2);
        foreach (['preparing', 'ready', 'delivered'] as $status) {
            $this->post("$this->admin/takeaway/{$order->id}/status", ['status' => $status]);
        }
        $this->assertNull($order->fresh()->paid_at);

        $this->get("$this->admin/cashier")->assertOk()
            ->assertSee(__('cashier.pending'))->assertSee('data-line-id="t'.$first->id.'"', false)->assertSee('data-line-id="o'.$order->id.'"', false)
            ->assertSee(vnd(30000))->assertSee(vnd(16000))
            ->assertSee(__('cashier.open'))->assertSee('id="cash-t'.$second->id.'"', false);

        // Thu tiền kèm số hoá đơn máy tính tiền; thu lần hai báo lỗi.
        $this->post("$this->admin/cashier/t{$first->id}/paid", ['bill_number' => ' 000123 '])
            ->assertSessionHas('status', __('cashier.paid', ['bill' => __('dinein.table_title', ['table' => 'A1']), 'total' => vnd(30000)]));
        $this->assertSame(['000123', $this->owner->id], [$first->fresh()->bill_number, $first->fresh()->paid_by]);
        $this->post("$this->admin/cashier/t{$first->id}/paid")->assertSessionHas('error', __('cashier.already_paid'));

        // Không nhập số hoá đơn vẫn thu được; bàn đang mở thu trước, đóng sau không quay lại hàng chờ.
        $this->post("$this->admin/cashier/o{$order->id}/paid", ['bill_number' => ''])->assertSessionHas('status');
        $this->assertNotNull($order->fresh()->paid_at);
        $this->post("$this->admin/cashier/t{$second->id}/paid", ['bill_number' => '000124']);
        $this->post("$this->admin/dinein/tables/{$second->id}/close")->assertSessionHas('status', __('dinein.closed', ['table' => 'A2']));

        // Bàn đang mở: Thanh toán và đóng bàn một lần; còn món chưa mang ra thì không đóng, không thu.
        $leaving = $this->table('A5', 2);
        $this->get("$this->admin/cashier")->assertSee(__('cashier.collect_close'));
        $leaving->lines()->update(['status' => 'new']);
        $this->post("$this->admin/cashier/t{$leaving->id}/paid", ['close' => '1'])->assertSessionHas('error', __('cashier.close_pending', ['table' => 'A5']));
        $this->assertNull($leaving->fresh()->paid_at);
        $leaving->lines()->update(['status' => 'served']);
        $this->post("$this->admin/cashier/t{$leaving->id}/paid", ['close' => '1', 'bill_number' => '000125'])
            ->assertSessionHas('status', __('cashier.paid_closed', ['bill' => __('dinein.table_title', ['table' => 'A5']), 'total' => vnd(20000)]));
        $this->assertNotNull($leaving->fresh()->closed_at);
        $this->assertSame([$this->owner->id, '000125'], [$leaving->fresh()->paid_by, $leaving->fresh()->bill_number]);

        $this->get("$this->admin/cashier", ['X-Board' => '1'])->assertSee(__('cashier.pending_empty'))
            ->assertSee(__('cashier.number', ['number' => '000123']))->assertSee(__('cashier.no_number'));

        // Nhập số hoá đơn sau; phiếu chưa thu thì không sửa số được.
        $this->put("$this->admin/cashier/o{$order->id}/number", ['bill_number' => 'HD-9'])->assertSessionHas('status');
        $this->assertSame('HD-9', $order->fresh()->bill_number);
        $third = $this->table('A3', 1);
        $this->put("$this->admin/cashier/t{$third->id}/number", ['bill_number' => '1'])->assertNotFound();
        $this->get("$this->admin/takeaway/{$order->id}")->assertSee(__('cashier.number', ['number' => 'HD-9']));
    }

    public function test_reconcile_shows_unpaid_and_missing_bill_numbers(): void
    {
        $paid = $this->table('A1', 2);
        $this->post("$this->admin/dinein/tables/{$paid->id}/close");
        $this->post("$this->admin/cashier/t{$paid->id}/paid", ['bill_number' => '000123']);
        $unpaid = $this->table('A2', 5);
        $this->post("$this->admin/dinein/tables/{$unpaid->id}/close");
        $noNumber = $this->takeaway(1);
        $noNumber->update(['status' => OrderStatus::Delivered]);
        $this->post("$this->admin/cashier/o{$noNumber->id}/paid");
        $cancelled = $this->takeaway(9);
        $cancelled->update(['status' => OrderStatus::Cancelled]);

        // Đã thu 28.000 (2 phiếu), chưa thu 50.000 (1 phiếu), 1 phiếu đã thu chưa có số hoá đơn; đơn huỷ không tính.
        $this->get("$this->admin/cashier/reconcile")->assertOk()
            ->assertSee(vnd(28000))->assertSee(vnd(50000))->assertSee(__('cashier.sum_unpaid_detail', ['count' => 1]))
            ->assertSee(__('cashier.unpaid'))->assertDontSee(vnd(72000));
        $this->get("$this->admin/cashier/reconcile?paid[]=unpaid")->assertSee(__('dinein.table_title', ['table' => 'A2']))
            ->assertDontSee(__('cashier.number', ['number' => '000123']));
        $this->get("$this->admin/cashier/reconcile?number[]=missing&paid[]=paid")->assertSee(__('takeaway.number', ['code' => $noNumber->code()]))
            ->assertDontSee(__('dinein.table_title', ['table' => 'A1']));
        $this->get("$this->admin/cashier/reconcile?q=0123")->assertSee('000123')->assertDontSee(__('dinein.table_title', ['table' => 'A2']));
        $this->get("$this->admin/cashier/reconcile/export")->assertOk();
    }

    public function test_dashboard_has_service_tiles_above_screens(): void
    {
        $table = $this->table('A1', 1);
        $this->post("$this->admin/dinein/tables/{$table->id}/close");
        $this->table('A2', 1);

        $this->get("$this->admin")->assertOk()->assertSeeInOrder([
            __('ui.dashboard.service'),
            __('ui.dashboard.dinein'), trans_choice('ui.dashboard.dinein_count', 1),
            __('ui.dashboard.takeaway'), trans_choice('ui.dashboard.takeaway_count', 0),
            __('ui.dashboard.cashier'), trans_choice('ui.dashboard.cashier_count', 1),
        ])->assertDontSee(__('ui.dashboard.reservation'))
            // Phiếu chờ thu: chấm đỏ có số ở góc thẻ Thu ngân và ở Tổng quan trên menu trái.
            ->assertSee('<b class="tile-count" title="'.trans_choice('ui.dashboard.cashier_count', 1).'">1', false)
            ->assertSee(__('ui.nav.attention', ['count' => 1]));

        // Logo, tên quán về Tổng quan; các màn hình phục vụ không còn mục riêng ở menu trái, đang ở đó thì Tổng quan sáng.
        $this->get("$this->admin/cashier")->assertSee('class="sidebar-brand" href="'.url("$this->admin").'"', false)
            ->assertSee('href="'.url("$this->admin").'" class="active"', false)
            ->assertDontSee('">'.__('modules.cashier').'</a>', false)->assertDontSee('">'.__('modules.dinein').'</a>', false);

        // Nhân viên không có quyền thu tiền: không thấy ô Thu ngân.
        $this->actingAs($this->member($this->restaurant, 'staff'))->get("$this->admin")
            ->assertSee(__('ui.dashboard.dinein'))->assertDontSee('/admin/cashier"', false)->assertDontSee('nav-badge');
    }

    public function test_permissions_and_module_off(): void
    {
        $staff = $this->member($this->restaurant, 'staff');
        $this->actingAs($staff)->get("$this->admin/cashier")->assertForbidden();
        $staff->forceFill(['permissions' => ['cashier.reconcile']])->save();
        $this->actingAs($staff->fresh())->get("$this->admin/cashier")->assertRedirect("$this->admin/cashier/reconcile");
        $this->post("$this->admin/cashier/t1/paid")->assertForbidden();

        // Tắt Thu ngân: đóng bàn, giao đơn là coi như đã thu.
        app(ModuleManager::class)->disable($this->restaurant, 'cashier');
        $this->actingAs($this->owner);
        $table = $this->table('A1', 1);
        $this->post("$this->admin/dinein/tables/{$table->id}/close")->assertSessionHas('status', __('dinein.closed', ['table' => 'A1']));
        $this->assertNotNull($table->fresh()->paid_at);
        $this->get("$this->admin/cashier")->assertNotFound();
    }
}
