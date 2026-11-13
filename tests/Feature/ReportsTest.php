<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\DineIn\Models\Station;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Thống kê cho chủ quán: các tab hiện đúng số liệu, lọc theo khoảng ngày, xuất Excel, chỉ người có quyền. */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private string $base;

    private MenuItem $pho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 11:00'));
        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'menu', 'dinein', 'kitchen', 'takeaway', 'reservation', 'cashier', 'staff', 'schedule');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->base = "/{$this->restaurant->slug}/admin/reports";
        app(TenantContext::class)->set($this->restaurant);
        $category = MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Món']);
        $this->pho = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Phở bò',
            'price_dine_in' => 70000, 'price_takeaway' => 60000, 'dinein_station_id' => Station::orderBy('sort_order')->value('id')]);
        $this->actingAs($this->owner);
    }

    /** Một bàn: gọi 2 phở lúc 11:00, bếp xong sau $prep phút, bưng ra sau thêm $wait phút, đóng bàn. */
    private function visit(string $label, int $prep, int $wait, bool $cancelOne = false): DiningTable
    {
        $this->post("/{$this->restaurant->slug}/admin/dinein/tables/$label/order", ['lines' => [['item' => $this->pho->id, 'quantity' => 2]]]);
        $table = DiningTable::withoutGlobalScopes()->where('label', $label)->whereNull('closed_at')->sole();
        $line = $table->lines()->first();
        $line->update(['status' => 'served', 'done_at' => now()->addMinutes($prep), 'served_at' => now()->addMinutes($prep + $wait),
            'done_by' => $this->owner->id, 'served_by' => $this->owner->id]);

        if ($cancelOne) {
            $table->lines()->create([...collect($line->getAttributes())->except(['id', 'created_at', 'updated_at'])->all(),
                'quantity' => 1, 'status' => 'cancelled', 'cancelled_by' => $this->owner->id, 'cancelled_at' => now()]);
        }

        $this->post("/{$this->restaurant->slug}/admin/dinein/tables/{$table->id}/close");

        return $table;
    }

    public function test_tabs_show_figures_and_export(): void
    {
        $this->visit('A1', 10, 2);
        $this->visit('A2', 30, 8, true);
        $first = DiningTable::withoutGlobalScopes()->where('label', 'A1')->sole();
        $first->incidents()->create(['restaurant_id' => $this->restaurant->id, 'kind' => 'slow', 'created_by' => $this->owner->id]);
        $this->post("/{$this->restaurant->slug}/admin/takeaway", ['customer_name' => 'Lan', 'customer_phone' => '0987654321', 'pickup_date' => '2026-11-09',
            'pickup_time' => '12:00', 'lines' => [['item' => $this->pho->id, 'quantity' => 1]]]);
        $order = TakeawayOrder::withoutGlobalScopes()->sole();
        $order->update(['status' => 'delivered', 'ready_at' => '2026-11-09 12:10:00', 'delivered_at' => '2026-11-09 12:15:00']);

        // Tổng quan: doanh thu 2 bàn (140.000 + 140.000, món huỷ không tính) + 60.000 mang đi; cảnh báo phiếu chưa thu.
        $this->get($this->base)->assertOk()->assertSee(vnd(340000))->assertSee(__('reports.alerts_title'))
            ->assertSee(__('reports.alert_unpaid', ['count' => 3, 'total' => vnd(340000)]));

        // Doanh thu theo ngày (giờ) và theo tuần.
        $this->get("$this->base/revenue?period=day")->assertOk()->assertSee(__('reports.revenue_by_hour'))->assertSee('11h')->assertSee('12h');
        $this->get("$this->base/revenue?period=week")->assertOk()->assertSee(__('reports.revenue_by_day'))->assertSee(__('reports.week_title', ['from' => '09/11', 'to' => '15/11/2026']));
        $this->get("$this->base/revenue?period=month&date=2026-11-01")->assertOk()->assertSee(__('reports.month_title', ['month' => '11/2026']));

        // Tốc độ: làm trung bình 20 phút, chờ bưng 5 phút; 1/2 món quá 15 phút.
        $this->get("$this->base/service")->assertOk()->assertSee(__('reports.minutes', ['count' => '20,0']))
            ->assertSee(__('reports.minutes', ['count' => '5,0']))->assertSee('50%');
        $this->get("$this->base/service?limit=30")->assertSee(__('reports.over_limit', ['minutes' => 30]));

        // Chất lượng: 1 sự cố / 2 bàn, 1 món huỷ sau khi đã làm (hao hụt 70.000).
        $this->get("$this->base/quality")->assertOk()->assertSee('50,0')->assertSee(vnd(70000))->assertSee(__('dinein.incident_kinds.slow'));

        // Mang đi: làm xong trễ 10 phút.
        $this->get("$this->base/bookings")->assertOk()->assertSee(__('reports.takeaway_late_rate'))->assertSee('100%')->assertSee(__('reports.minutes', ['count' => '10,0']));
        $this->get("$this->base/staff")->assertOk()->assertSee($this->owner->name)->assertSee(__('reports.station_parts'));
        $this->get("$this->base/sales")->assertOk()->assertSee('Phở bò')->assertSee(__('reports.portions', ['count' => 5]));

        $this->get("$this->base/service/export")->assertOk()->assertHeader('content-disposition');

        // Khoảng ngày khác: không có dữ liệu.
        $this->get("$this->base/service?from=2026-10-01&to=2026-10-07")->assertSee(__('reports.no_data'));
    }

    public function test_only_people_with_permission(): void
    {
        $staff = $this->member($this->restaurant, 'manager');
        $this->actingAs($staff)->get($this->base)->assertForbidden();
        $this->actingAs($staff)->get("/{$this->restaurant->slug}/admin")->assertDontSee(__('reports.title'));
        $staff->forceFill(['permissions' => ['reports.view']])->save();
        $this->actingAs($staff->fresh())->get($this->base)->assertOk();
        $this->actingAs($this->owner)->get("/{$this->restaurant->slug}/admin")->assertSee('href="'.url($this->base).'"', false);
    }
}
