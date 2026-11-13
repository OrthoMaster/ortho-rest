<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\DineIn\Models\Incident;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuItemOption;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use App\Modules\Schedule\Duty;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\Week;
use App\Modules\Takeaway\DiscountType;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\OrderStatus;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DineInTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private string $base;

    private string $kitchenBase;

    private Station $kitchen;

    private Station $bar;

    private Area $area;

    private MenuItem $comTam;

    private MenuItem $tra;

    /** @var array<string, MenuItemOption> */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00'));
        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'menu', 'dinein', 'kitchen', 'staff', 'schedule');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->base = "/{$this->restaurant->slug}/admin/dinein";
        $this->kitchenBase = "/{$this->restaurant->slug}/admin/kitchen";
        $this->actingAs($this->owner);

        app(TenantContext::class)->set($this->restaurant);
        [$this->kitchen, $this->bar] = Station::orderBy('sort_order')->get()->all();
        $this->area = Area::sole();
        $category = MenuCategory::create(['name' => 'Món chính']);
        $this->comTam = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cơm tấm', 'price_dine_in' => 85000, 'dinein_station_id' => $this->kitchen->id]);
        $this->tra = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Trà đá', 'price_dine_in' => 10000, 'dinein_station_id' => $this->bar->id]);

        $meat = $this->comTam->optionGroups()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Thịt', 'is_required' => true]);
        $sauce = $this->comTam->optionGroups()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Nước chấm', 'is_multiple' => true]);
        foreach ([[$meat, 'Sườn', 0], [$meat, 'Sườn cọng', 15000], [$sauce, 'Không ớt', 0], [$sauce, 'Nhiều tỏi', 2000]] as [$group, $name, $price]) {
            $this->options[$name] = $group->options()->create(['restaurant_id' => $this->restaurant->id, 'name' => $name, 'price' => $price]);
        }
        app(TenantContext::class)->forget();
    }

    /** Bật Đặt mang đi, tạo một đơn mang đi nhận hôm nay 12:30 có cơm tấm (bếp) và trà đá (bar). */
    private function takeawayOrder(): TakeawayOrder
    {
        $this->enableModules($this->restaurant, 'takeaway');
        $this->comTam->newQuery()->withoutGlobalScopes()->whereKey([$this->comTam->id, $this->tra->id])->update(['price_takeaway' => 50000]);

        $this->post("/{$this->restaurant->slug}/admin/takeaway", [
            'customer_name' => 'Chị Mai', 'customer_phone' => '0987654321', 'pickup_date' => '2026-11-09', 'pickup_time' => '12:30',
            'lines' => [
                ['item' => $this->comTam->id, 'quantity' => 1, 'options' => [$this->options['Sườn']->id, $this->options['Không ớt']->id], 'note' => 'Ít cơm'],
                ['item' => $this->comTam->id, 'quantity' => 2, 'options' => [$this->options['Sườn cọng']->id]],
                ['item' => $this->tra->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        return TakeawayOrder::withoutGlobalScopes()->latest('id')->first();
    }

    public function test_station_screen_has_one_ticket_per_table_and_takeaway_orders(): void
    {
        // Hai lần gọi của bàn A1 gộp một phiếu, mỗi món một dòng có nút Xong; không ghi Lượt.
        $this->order('A1', [$this->comTamLine(['Sườn'], 1, null)]);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:10'));
        $this->order('A1', [$this->comTamLine(['Sườn cọng'], 1, null)]);
        $order = $this->takeawayOrder();
        $items = $order->items()->withoutGlobalScopes()->orderBy('id')->get();

        $this->get("$this->kitchenBase/{$this->kitchen->id}")->assertOk()
            ->assertSeeInOrder(['A1', __('kitchen.ordered_ago', ['time' => '10 phút']), 'Sườn', 'Sườn cọng'])
            ->assertDontSee(__('dinein.round', ['round' => 2]))
            // Đơn mang đi: phiếu riêng, giờ giao và còn bao lâu; món khác tuỳ chọn là dòng riêng.
            ->assertSee('is-takeaway', false)->assertSee($order->code())
            ->assertSee(__('kitchen.pickup_left', ['time' => '12:30', 'left' => '20 phút']))
            ->assertSee('data-line-id="t'.$items[0]->id.'"', false)->assertSee('data-line-id="t'.$items[1]->id.'"', false)
            ->assertDontSee('data-line-id="t'.$items[2]->id.'"', false);
        $this->get("$this->kitchenBase/{$this->bar->id}")->assertSee('data-line-id="t'.$items[2]->id.'"', false);

        // Bếp báo xong món mang đi: đơn sang Đang làm, món rời màn hình khu, vào danh sách Đơn đã xong (không còn khung Vừa xong).
        $this->post("$this->kitchenBase/{$this->kitchen->id}/done", ['takeaway' => [$items[0]->id]])->assertSessionHas('status');
        $this->assertNotNull($items[0]->fresh()->done_at);
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
        $this->get("$this->kitchenBase/{$this->kitchen->id}")->assertDontSee('data-line-id="t'.$items[0]->id.'"', false)
            ->assertSee(__('kitchen.finished').' (1)')->assertSee(__('kitchen.finished_today'))->assertSee(__('kitchen.takeaway_tag').' '.$order->code().' · 1 × Cơm tấm');
        // Không báo được món của khu khác.
        $this->post("$this->kitchenBase/{$this->kitchen->id}/done", ['takeaway' => [$items[2]->id]]);
        $this->assertNull($items[2]->fresh()->done_at);
    }

    public function test_takeaway_prep_screen_packs_and_delivers(): void
    {
        $order = $this->takeawayOrder();
        $item = $order->items()->withoutGlobalScopes()->first();

        // Đơn mang đi luôn đứng đầu danh sách màn hình, ở cả Tổng quan để vào nhanh.
        $this->get("$this->kitchenBase")->assertOk()->assertSeeInOrder([__('kitchen.prep'), $this->kitchen->name]);
        $this->get("/{$this->restaurant->slug}/admin")->assertOk()->assertSeeInOrder([__('kitchen.screens'), __('kitchen.prep'), $this->kitchen->name, $this->bar->name])
            ->assertSee("$this->kitchenBase/{$this->bar->id}", false)
            // Menu trái không còn mục Màn hình khu vực: vào từ Tổng quan.
            ->assertDontSee('">'.__('modules.kitchen').'</a>', false);
        $this->get("$this->kitchenBase/takeaway")->assertOk()->assertSee($order->code())->assertSee('Chị Mai')
            ->assertSee(__('kitchen.items_done', ['done' => 0, 'total' => 3]))->assertDontSee('data-line-id="d', false);

        $this->post("$this->kitchenBase/{$this->kitchen->id}/done", ['takeaway' => [$item->id]]);
        $this->get("$this->kitchenBase/takeaway")->assertSee('data-line-id="d'.$item->id.'"', false)
            ->assertSee(__('kitchen.pack_confirm_missing', ['count' => 2]));

        $this->post("$this->kitchenBase/takeaway/{$order->id}", ['status' => 'ready'])->assertSessionHas('status', __('kitchen.packed', ['code' => $order->code()]));
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
        // Đơn đã đóng gói không còn ở màn hình khu.
        $this->get("$this->kitchenBase/{$this->bar->id}", ['X-Board' => '1'])->assertDontSee($order->code());

        $this->post("$this->kitchenBase/takeaway/{$order->id}", ['status' => 'delivered']);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        // Đơn đã giao sang cột phải (không có ca thì tính trong ngày), bấm xem chi tiết trong popup.
        $this->get("$this->kitchenBase/takeaway")->assertSee(__('kitchen.prep_empty'))
            ->assertSee(__('kitchen.delivered_today'))->assertSee('data-show-order="'.$order->id.'"', false)
            ->assertSee('data-order-detail="'.$order->id.'"', false)->assertSee('0987654321')
            ->assertDontSee('/admin/takeaway/'.$order->id.'"', false);

        // Nhân viên có quyền đổi trạng thái đơn mang đi mở được dù không ở khu nào.
        $waiter = $this->member($this->restaurant, 'staff');
        $this->actingAs($waiter)->get("$this->kitchenBase")->assertRedirect("$this->kitchenBase/takeaway");
        $this->get("$this->kitchenBase/{$this->kitchen->id}")->assertForbidden();
    }

    private function order(string $table, array $lines)
    {
        return $this->post("$this->base/tables/$table/order", ['lines' => $lines]);
    }

    private function comTamLine(array $options = ['Sườn cọng', 'Không ớt', 'Nhiều tỏi'], int $quantity = 2, ?string $note = 'Ít cơm'): array
    {
        return ['item' => $this->comTam->id, 'quantity' => $quantity, 'options' => array_map(fn ($name) => $this->options[$name]->id, $options), 'note' => $note];
    }

    public function test_enabling_creates_stations_area_and_manager_permissions(): void
    {
        $this->assertSame(['Bếp', 'Quầy nước'], [$this->kitchen->name, $this->bar->name]);
        $this->assertSame('A', $this->area->code);

        $manager = $this->member($this->restaurant, 'manager');
        $staff = $this->member($this->restaurant, 'staff');

        foreach (['dinein.order', 'dinein.close', 'dinein.cancel', 'kitchen.stations', 'dinein.settings'] as $key) {
            $this->assertTrue($manager->can($key), $key);
        }
        $this->assertTrue($staff->can('dinein.order'));
        $this->assertTrue($staff->can('dinein.close'));
        $this->assertFalse($staff->can('dinein.cancel'));
        $this->assertFalse($staff->can('kitchen.stations'));

        $this->get($this->base)->assertRedirect("$this->base/tables");
        $this->get("$this->base/tables")->assertOk()->assertSee('A1')->assertSee('A10')->assertDontSee('A11');
    }

    public function test_order_copies_price_and_options_and_routes_to_stations(): void
    {
        $this->order('a3', [$this->comTamLine(), ['item' => $this->tra->id, 'quantity' => 3]])
            ->assertRedirect("$this->base/tables/A3")->assertSessionHas('status', __('dinein.order_sent', ['count' => 5]));

        $table = DiningTable::withoutGlobalScopes()->sole();
        $this->assertSame('A3', $table->label);
        $this->assertSame($this->owner->id, $table->opened_by);

        $lines = OrderLine::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame(85000 + 15000 + 2000, $lines[0]->unit_price);
        $this->assertSame('Sườn cọng, Không ớt, Nhiều tỏi', $lines[0]->optionText());
        $this->assertSame(['Thịt: Sườn cọng', 'Nước chấm: Không ớt, Nhiều tỏi'], $lines[0]->optionLines());
        $this->assertSame('Ít cơm', $lines[0]->note);
        $this->assertSame($this->kitchen->id, $lines[0]->dinein_station_id, 'Món chưa chọn khu vào khu đầu tiên.');
        $this->assertSame($this->bar->id, $lines[1]->dinein_station_id);
        $this->assertSame(LineStatus::New, $lines[1]->status);

        // Sửa menu sau đó không đổi món đã gọi; gọi thêm là lượt 2 cùng bàn.
        $this->comTam->update(['price_dine_in' => 99000]);
        $this->order('A3', [['item' => $this->tra->id, 'quantity' => 1]]);
        $this->assertSame(1, DiningTable::withoutGlobalScopes()->count());
        $this->assertSame([1, 1, 2], OrderLine::withoutGlobalScopes()->orderBy('id')->pluck('round')->all());

        $this->get("$this->base/tables/A3")->assertOk()
            ->assertSee(__('dinein.round', ['round' => 2]))
            ->assertSee(vnd(2 * 102000 + 4 * 10000));
        $this->get("$this->base/tables")->assertSee(vnd(244000));
    }

    public function test_order_validation(): void
    {
        $this->order('A3', [$this->comTamLine(['Không ớt'])])->assertSessionHasErrors(['lines.0' => __('dinein.option_required', ['name' => 'Cơm tấm', 'group' => 'Thịt'])]);
        $this->order('A3', [$this->comTamLine(['Sườn', 'Sườn cọng'])])->assertSessionHasErrors(['lines.0' => __('dinein.option_single', ['name' => 'Cơm tấm', 'group' => 'Thịt'])]);

        // Tuỳ chọn của món khác.
        $this->order('A3', [['item' => $this->tra->id, 'quantity' => 1, 'options' => [$this->options['Sườn']->id]]])->assertSessionHasErrors('lines.0');

        $this->tra->update(['is_sold_out' => true]);
        $this->order('A3', [['item' => $this->tra->id, 'quantity' => 1]])->assertSessionHasErrors(['lines.0' => __('dinein.item_sold_out', ['name' => 'Trà đá'])]);
        $this->order('A3', [])->assertSessionHasErrors(['lines' => __('dinein.order_empty')]);

        // Bàn ngoài số bàn của khu, khu không có.
        $this->order('A11', [$this->comTamLine()])->assertNotFound();
        $this->get("$this->base/tables/Z1")->assertNotFound();
        $this->post("$this->base/tables/go", ['label' => 'a11'])->assertSessionHasErrors(['label' => __('dinein.table_out_of_range', ['area' => $this->area->name, 'count' => 10, 'code' => 'A'])]);
        $this->post("$this->base/tables/go", ['label' => 'Z1'])->assertSessionHasErrors(['label' => __('dinein.table_unknown_area', ['code' => 'Z'])]);
        $this->post("$this->base/tables/go", ['label' => 'a7'])->assertRedirect("$this->base/tables/A7");

        $this->assertSame(0, OrderLine::withoutGlobalScopes()->count());
    }

    public function test_station_done_alerts_waiter_then_serve_and_close(): void
    {
        $waiter = $this->member($this->restaurant, 'staff');
        $this->actingAs($waiter)->order('A3', [$this->comTamLine(), ['item' => $this->tra->id, 'quantity' => 1]]);
        [$rice, $tea] = OrderLine::withoutGlobalScopes()->orderBy('id')->get()->all();

        // Bếp chỉ thấy món của bếp.
        $this->actingAs($this->owner)->get("$this->kitchenBase/{$this->kitchen->id}")->assertOk()->assertSee('Cơm tấm')->assertDontSee('Trà đá');
        $this->get("$this->kitchenBase/{$this->kitchen->id}", ['X-Board' => '1'])->assertOk()->assertDontSee('<html', false)->assertSee('data-line-id="'.$rice->id.'"', false);

        $this->actingAs($waiter)->getJson("$this->base/poll")->assertOk()->assertJsonCount(0, 'ready');

        // Không đóng được khi còn món chưa mang ra.
        $table = DiningTable::withoutGlobalScopes()->sole();
        $this->post("$this->base/tables/{$table->id}/close")->assertSessionHas('error', __('dinein.close_pending'));

        $this->actingAs($this->owner)->post("$this->kitchenBase/{$this->kitchen->id}/done", ['lines' => [$rice->id, $tea->id]])
            ->assertSessionHas('status', trans_choice('kitchen.done_count', 1, ['count' => 1]));
        $this->assertSame(LineStatus::New, $tea->fresh()->status, 'Món của quầy nước không báo xong từ màn hình bếp.');
        $this->assertSame($this->owner->id, $rice->fresh()->done_by);

        $this->actingAs($waiter)->getJson("$this->base/poll")->assertOk()
            ->assertJsonCount(1, 'ready')
            ->assertJsonPath('ready.0.table', 'A3')
            ->assertJsonPath('ready.0.text', '2 × Cơm tấm');

        // Người phục vụ khác không nhận, trừ khi được phân công khu vực đó.
        $other = $this->member($this->restaurant, 'staff');
        $this->actingAs($other)->getJson("$this->base/poll")->assertJsonCount(0, 'ready');
        Assignment::create(['restaurant_id' => $this->restaurant->id, 'work_date' => '2026-11-09', 'area_id' => $this->area->id, 'user_id' => $other->id]);
        // Lịch tuần còn là bản nháp thì chưa tính là đang làm.
        $this->actingAs($other)->getJson("$this->base/poll")->assertJsonCount(0, 'ready');
        Week::create(['restaurant_id' => $this->restaurant->id, 'week_start' => '2026-11-09', 'published_at' => now()]);
        $this->actingAs($other)->getJson("$this->base/poll")->assertJsonCount(1, 'ready');

        $this->actingAs($waiter)->post("$this->base/lines/{$rice->id}/serve")->assertSessionHas('status');
        $this->assertSame(LineStatus::Served, $rice->fresh()->status);
        $this->post("$this->base/lines/{$rice->id}/serve")->assertStatus(422);

        // Nhân viên huỷ được món chưa làm xong.
        $this->post("$this->base/lines/{$tea->id}/cancel")->assertSessionHas('status');
        $this->assertSame(LineStatus::Cancelled, $tea->fresh()->status);

        $this->post("$this->base/tables/{$table->id}/close")->assertRedirect("$this->base/tables");
        $this->assertNotNull($table->fresh()->closed_at);
        $this->get("$this->base/tables/A3")->assertSee(__('dinein.table_free'));

        // Bàn mở lại là một lượt khách mới.
        $this->order('A3', [['item' => $this->tra->id, 'quantity' => 1]]);
        $this->assertSame(2, DiningTable::withoutGlobalScopes()->count());
    }

    public function test_cancelling_a_done_line_needs_permission(): void
    {
        $staff = $this->member($this->restaurant, 'staff');
        $this->actingAs($staff)->order('A1', [['item' => $this->tra->id, 'quantity' => 1]]);
        $line = OrderLine::withoutGlobalScopes()->sole();
        $line->update(['status' => LineStatus::Done]);

        $this->post("$this->base/lines/{$line->id}/cancel")->assertForbidden();
        $this->actingAs($this->member($this->restaurant, 'manager'))->post("$this->base/lines/{$line->id}/cancel")->assertSessionHas('status');
        $this->assertSame(LineStatus::Cancelled, $line->fresh()->status);
    }

    public function test_station_screen_follows_assignment_and_shift(): void
    {
        $cook = $this->member($this->restaurant, 'staff');
        $this->actingAs($cook)->get("$this->kitchenBase")->assertForbidden();
        $this->assertFalse(Duty::canOpenStation($cook, $this->kitchen->id));

        // Ca làm 10:00–14:00 thứ Hai; bây giờ 12:00 thứ Hai.
        $shift = Shift::create(['restaurant_id' => $this->restaurant->id, 'type' => ShiftType::Work, 'weekday' => 1, 'name' => 'Ca trưa', 'start_time' => '10:00', 'end_time' => '14:00']);

        $schedule = "/{$this->restaurant->slug}/admin/schedule/assignments";
        $this->actingAs($this->owner)->postJson($schedule, ['user' => $cook->id, 'date' => '2026-11-09', 'slot' => (string) $shift->id, 'lane' => 's'.$this->kitchen->id])->assertOk();
        $this->postJson($schedule, ['user' => $this->owner->id, 'date' => '2026-11-09', 'slot' => (string) $shift->id, 'lane' => 'a'.$this->area->id])->assertOk();
        $this->assertSame(2, Assignment::withoutGlobalScopes()->count());
        // Còn là bản nháp thì chưa mở được màn hình khu; công bố rồi mới mở.
        $this->actingAs($cook)->get("$this->kitchenBase")->assertForbidden();
        $this->actingAs($this->owner)->post("/{$this->restaurant->slug}/admin/schedule/week/publish", ['date' => '2026-11-09']);

        $this->actingAs($cook)->get("$this->kitchenBase")->assertRedirect("$this->kitchenBase/{$this->kitchen->id}");
        $this->get("$this->kitchenBase/{$this->kitchen->id}")->assertOk();
        $this->get("$this->kitchenBase/{$this->bar->id}")->assertForbidden();
        $this->get($this->base)->assertRedirect("$this->base/tables");

        $this->actingAs($this->owner)->order('A2', [$this->comTamLine()]);
        $this->actingAs($cook)->getJson("$this->base/poll")->assertJsonPath('stations.0.id', $this->kitchen->id)->assertJsonCount(1, 'stations.0.ids');

        // Hết ca thì không còn ở bếp.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00'));
        $this->get("$this->kitchenBase/{$this->kitchen->id}")->assertForbidden();
        $this->getJson("$this->base/poll")->assertJsonCount(0, 'stations');

        // Tắt Lịch làm việc thì phân công không còn tác dụng.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00'));
        $this->get("$this->kitchenBase/{$this->kitchen->id}")->assertOk();
        app(ModuleManager::class)->disable($this->restaurant, 'schedule');
        $this->get("$this->kitchenBase/{$this->kitchen->id}")->assertForbidden();
    }

    public function test_shift_crossing_midnight_counts_for_previous_day(): void
    {
        $shift = Shift::create(['restaurant_id' => $this->restaurant->id, 'type' => ShiftType::Work, 'weekday' => 1, 'name' => 'Ca khuya', 'start_time' => '18:00', 'end_time' => '02:00']);
        Assignment::create(['restaurant_id' => $this->restaurant->id, 'work_date' => '2026-11-09', 'shift_id' => $shift->id, 'dinein_station_id' => $this->bar->id, 'user_id' => $this->owner->id]);
        Week::create(['restaurant_id' => $this->restaurant->id, 'week_start' => '2026-11-09', 'published_at' => now()]);

        app(TenantContext::class)->set($this->restaurant);
        $this->assertSame([], Duty::stationIds($this->owner), 'Chưa đến ca.');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 19:00'));
        $this->assertSame([$this->bar->id], Duty::stationIds($this->owner));
        $this->travelTo(CarbonImmutable::parse('2026-11-10 01:30'));
        $this->assertSame([$this->bar->id], Duty::stationIds($this->owner));
        $this->travelTo(CarbonImmutable::parse('2026-11-10 02:30'));
        $this->assertSame([], Duty::stationIds($this->owner));
    }

    public function test_every_step_of_a_table_is_logged_with_who_and_when(): void
    {
        $waiter = $this->member($this->restaurant, 'staff', ['name' => 'Lan']);
        $this->actingAs($waiter)->order('A1', [$this->comTamLine(['Sườn cọng'], 2, 'Ít cơm')]);
        $rice = OrderLine::withoutGlobalScopes()->sole();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:14'));
        $this->actingAs($this->owner)->post("$this->kitchenBase/{$this->kitchen->id}/done", ['lines' => [$rice->id]]);
        // Đơn đã xong trong ca: giờ nhận và giờ xong.
        $this->get("$this->kitchenBase/{$this->kitchen->id}", ['X-Board' => '1'])->assertSee(__('kitchen.finished_times', ['ordered' => '12:00', 'done' => '12:14']));

        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:16'));
        $this->actingAs($waiter)->post("$this->base/lines/{$rice->id}/serve");
        $this->assertSame($waiter->id, $rice->fresh()->served_by);
        $this->post("$this->base/tables/{$rice->dinein_table_id}/close");

        $logs = ActivityLog::where('area', 'dinein')->orderBy('id')->get();
        $name = '2 × Cơm tấm (Thịt: Sườn cọng; Ít cơm)';
        $this->assertSame([
            __('dinein.log_ordered', ['table' => 'A1', 'round' => 1, 'count' => 2]),
            __('dinein.log_done', ['station' => $this->kitchen->name, 'table' => 'A1', 'count' => 2]),
            __('dinein.log_served', ['table' => 'A1', 'name' => $name]),
            __('dinein.log_closed', ['table' => 'A1']),
        ], $logs->pluck('summary')->all());
        $this->assertSame([$waiter->id, $this->owner->id, $waiter->id, $waiter->id], $logs->pluck('user_id')->all());
        $this->assertSame([$name.' · '.$this->kitchen->name], $logs[0]->changes);
        $this->assertSame(['gọi 12:00 · xong 12:14 · mang ra 12:16'], $logs[2]->changes);
    }

    public function test_ready_alert_goes_only_to_staff_serving_that_area_now(): void
    {
        $orderer = $this->member($this->restaurant, 'staff');
        $server = $this->member($this->restaurant, 'staff');
        $this->actingAs($orderer)->order('A1', [$this->comTamLine()]);
        $rice = OrderLine::withoutGlobalScopes()->sole();
        $this->actingAs($this->owner)->post("$this->kitchenBase/{$this->kitchen->id}/done", ['lines' => [$rice->id]]);

        // Khu vực chưa có ai được phân công: báo cho người gọi món.
        $this->actingAs($orderer)->getJson("$this->base/poll")->assertJsonCount(1, 'ready');
        $this->actingAs($server)->getJson("$this->base/poll")->assertJsonCount(0, 'ready');

        // Có người được phân công phục vụ khu vực này trong ca: chỉ người đó nhận.
        Assignment::create(['restaurant_id' => $this->restaurant->id, 'work_date' => '2026-11-09', 'area_id' => $this->area->id, 'user_id' => $server->id]);
        Week::create(['restaurant_id' => $this->restaurant->id, 'week_start' => '2026-11-09', 'published_at' => now()]);
        $this->actingAs($orderer)->getJson("$this->base/poll")->assertJsonCount(0, 'ready');
        $this->actingAs($server)->getJson("$this->base/poll")->assertJsonCount(1, 'ready');

        // Màn hình bàn: dấu cảnh báo ở bàn có món chờ mang ra; chi tiết bàn có nút Đã mang ra, tự tải lại phần món.
        $this->get("$this->base/tables")->assertSee('tile-alert', false)->assertSee(__('dinein.ready_count', ['count' => 2]));
        $this->get("$this->base/tables", ['X-Board' => '1'])->assertDontSee('<html', false)->assertSee('tile-alert', false);
        $this->get("$this->base/tables/A1", ['X-Board' => '1'])->assertDontSee('<html', false)
            ->assertSee('line-head', false)->assertSee(__('dinein.serve'))
            ->assertSeeInOrder([__('dinein.status.done'), __('dinein.waiting_serve')]);
        $this->post("$this->base/lines/{$rice->id}/serve");
        $this->get("$this->base/tables")->assertDontSee('tile-alert', false);
        $this->getJson("$this->base/poll")->assertJsonCount(0, 'ready');
    }

    public function test_bill_merges_rounds_like_a_receipt(): void
    {
        $this->order('A1', [$this->comTamLine(['Sườn cọng'], 1, 'Ít cơm'), ['item' => $this->tra->id, 'quantity' => 1]]);
        $this->order('A1', [$this->comTamLine(['Sườn cọng'], 2, null), $this->comTamLine(['Sườn'], 1, null)]);

        $this->get("$this->base/tables/A1")->assertOk()->assertSee(__('dinein.bill'))
            ->assertSee(__('dinein.bill_title', ['table' => 'A1']))
            // Cùng món, cùng tuỳ chọn ở hai lượt gộp thành 3 phần; khác tuỳ chọn là dòng riêng.
            ->assertSeeInOrder(['bill-qty">3<', 'Cơm tấm', 'Thịt: Sườn cọng', vnd(300000)], false)
            ->assertSee(__('dinein.bill_count', ['count' => 5]))->assertSee(vnd(300000 + 85000 + 10000));
    }

    public function test_guest_by_phone_incident_and_offer_for_next_visit(): void
    {
        // Trà đá không qua khu nên mang ra luôn, đóng bàn được ngay.
        $this->tra->newQuery()->withoutGlobalScopes()->whereKey($this->tra->id)->update(['dinein_station_id' => null]);
        $this->order('A1', [['item' => $this->tra->id, 'quantity' => 2]]);
        $table = DiningTable::withoutGlobalScopes()->sole();
        $tables = "$this->base/tables/{$table->id}";

        // Gắn khách theo số điện thoại; ghi sự cố.
        $this->post("$tables/guest", ['name' => 'Chị Mai', 'phone' => '12ab'])->assertSessionHasErrorsIn('guest-dialog', 'phone');
        $this->post("$tables/guest", ['name' => 'Chị Mai', 'phone' => '0987 654 321'])->assertSessionHas('status');
        $this->post("$tables/incidents", ['kind' => 'slow', 'note' => 'Chờ trà 30 phút'])->assertSessionHas('status');
        $this->get("$this->base/tables/A1")->assertSee('Chị Mai')->assertSee('0987654321')
            ->assertSee(__('dinein.incident_kinds.slow'))->assertSee('Chờ trà 30 phút');

        // Tặng ưu đãi lần sau (cần quyền), rồi đóng bàn.
        $staff = $this->member($this->restaurant, 'staff');
        $this->actingAs($staff)->post("$tables/gift", ['type' => 'percent', 'percent' => 10, 'reason' => 'Món ra chậm'])->assertForbidden();
        $this->actingAs($this->owner)->post("$tables/gift", ['type' => 'percent', 'percent' => 10, 'reason' => 'Món ra chậm', 'expires_on' => '2027-02-01', 'for_customer' => '1'])->assertSessionHas('status');
        $this->post("$tables/close");

        // Lần sau khách quay lại: gắn theo số điện thoại là thấy ưu đãi, áp vào phiếu, dùng một lần.
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00'));
        $this->order('A2', [['item' => $this->tra->id, 'quantity' => 5]]);
        $next = DiningTable::withoutGlobalScopes()->whereNull('closed_at')->sole();
        $this->post("$this->base/tables/{$next->id}/guest", ['name' => 'Mai', 'phone' => '0987654321']);
        $offer = CustomerOffer::withoutGlobalScopes()->sole();
        $this->assertSame($next->fresh()->customer_id, $offer->customer_id, 'Cùng số điện thoại là cùng khách.');
        $this->get("$this->base/tables/A2")->assertSee(__('dinein.offers_title'))->assertSee('Món ra chậm')->assertSee(__('dinein.guest_visits', ['count' => 2]));

        $this->post("$this->base/tables/{$next->id}/offers/{$offer->id}")->assertSessionHas('status', __('dinein.offer_applied'));
        $this->get("$this->base/tables/A2")->assertSee(__('dinein.bill_due'))->assertSee('−'.vnd(5000))->assertSee(vnd(45000));
        $this->post("$this->base/tables/{$next->id}/offers/{$offer->id}")->assertSessionHas('error');

        // Bỏ giảm thì ưu đãi trả lại; áp lại rồi đóng bàn thì lưu số tiền giảm.
        $this->delete("$this->base/tables/{$next->id}/discount");
        $this->assertNull($offer->fresh()->used_at);
        $this->post("$this->base/tables/{$next->id}/offers/{$offer->id}");
        $this->post("$this->base/tables/{$next->id}/close");
        $this->assertSame(5000, $next->fresh()->discount_amount);
        $this->assertNotNull($offer->fresh()->used_at);
        $this->assertTrue(ActivityLog::where('summary', __('dinein.log_closed', ['table' => 'A2']))->first()->changes === [
            __('dinein.log_opened_at', ['time' => '12:00 20/11']), __('dinein.log_items', ['count' => 5]),
            __('dinein.log_total', ['total' => vnd(50000)]), __('dinein.log_discount', ['amount' => vnd(5000)]), __('dinein.log_customer', ['name' => 'Mai']),
        ]);

        // Trang Sự cố (quản lý): lọc theo ngày, xuất Excel.
        $this->get("$this->base/incidents?from=2026-11-09&to=2026-11-20")->assertOk()->assertSee('Chờ trà 30 phút')->assertSee('Mai');
        $this->get("$this->base/incidents/export?from=2026-11-09&to=2026-11-20")->assertOk();
        $this->actingAs($staff)->get("$this->base/incidents")->assertForbidden();
    }

    public function test_reserved_guest_is_seated_at_a_table_and_completed_on_close(): void
    {
        $this->enableModules($this->restaurant, 'reservation');
        $tenant = ['restaurant_id' => $this->restaurant->id];
        $customer = Customer::create([...$tenant, 'name' => 'Anh Tuấn', 'phone' => '0911222333']);
        $reservation = Reservation::create([...$tenant, 'customer_id' => $customer->id, 'area_id' => $this->area->id,
            'reserved_at' => '2026-11-09 12:30', 'ends_at' => '2026-11-09 14:00', 'party_size' => 4,
            'status' => ReservationStatus::Confirmed, 'source' => ReservationSource::Phone]);
        $status = "/{$this->restaurant->slug}/admin/reservations/{$reservation->id}/status";

        // Hôm nay: nút Khách đã đến mở hộp chọn bàn.
        $this->get("/{$this->restaurant->slug}/admin/reservations")->assertSee('data-seat=', false)->assertSee('id="seat-dialog"', false);

        // Bàn A2 đã có khách khác thì báo lỗi, đặt bàn vẫn chưa đổi.
        $this->order('A2', [['item' => $this->tra->id, 'quantity' => 1]]);
        DiningTable::withoutGlobalScopes()->sole()->update(['customer_id' => Customer::create([...$tenant, 'name' => 'Khác', 'phone' => '0900111222'])->id]);
        $this->post($status, ['status' => 'seated', 'table' => 'Z1'])->assertSessionHasErrors('status');
        $this->post($status, ['status' => 'seated', 'table' => 'A2'])->assertSessionHasErrors(['status' => __('dinein.seat_taken', ['table' => 'A2'])]);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);

        // Xếp vào bàn trống A3: mở lượt bàn có sẵn thông tin khách.
        $this->post($status, ['status' => 'seated', 'table' => 'a3'])->assertSessionHas('status', __('reservation.seated_at', ['table' => 'A3']));
        $table = DiningTable::withoutGlobalScopes()->where('label', 'A3')->sole();
        $this->assertSame([$customer->id, $reservation->id], [$table->customer_id, $table->reservation_id]);
        $this->assertSame(ReservationStatus::Seated, $reservation->fresh()->status);
        $this->get("$this->base/tables/A3")->assertSee('Anh Tuấn')->assertSee(__('dinein.from_reservation', ['time' => '12:30', 'party' => 4]));
        $this->get("/{$this->restaurant->slug}/admin/reservations/{$reservation->id}")->assertSee(__('reservation.table'))->assertSee('A3');

        // Khách về, đóng bàn: đặt bàn chuyển sang Hoàn tất.
        $this->post("$this->base/tables/{$table->id}/close");
        $this->assertSame(ReservationStatus::Completed, $reservation->fresh()->status);

        // Không chọn bàn thì chỉ đổi trạng thái như trước.
        $other = $reservation->replicate()->fill(['status' => ReservationStatus::Confirmed]);
        $other->save();
        $this->post("/{$this->restaurant->slug}/admin/reservations/{$other->id}/status", ['status' => 'seated', 'table' => ''])
            ->assertSessionHas('status', __('reservation.status_changed', ['status' => ReservationStatus::Seated->label()]));
        $this->assertSame(0, DiningTable::withoutGlobalScopes()->where('reservation_id', $other->id)->count());
    }

    public function test_anonymous_voucher_with_qr_page_applied_by_code_at_another_table(): void
    {
        $this->tra->newQuery()->withoutGlobalScopes()->whereKey($this->tra->id)->update(['dinein_station_id' => null]);
        $this->order('A1', [['item' => $this->tra->id, 'quantity' => 2]]);
        $table = DiningTable::withoutGlobalScopes()->sole();

        // Bàn không gắn khách: tặng voucher không tên, mở luôn mã QR.
        $this->post("$this->base/tables/{$table->id}/gift", ['type' => 'amount', 'thousands' => 20, 'reason' => 'Sai món'])
            ->assertSessionHas('status', __('dinein.voucher_saved'));
        $voucher = CustomerOffer::withoutGlobalScopes()->sole();
        $this->assertNull($voucher->customer_id);
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{6}$/', $voucher->code);
        $this->get("$this->base/tables/A1")->assertSee($voucher->code)->assertSee('id="voucher-'.$voucher->id.'"', false)
            ->assertSee($voucher->publicUrl(), false);

        // Khách mở trang ưu đãi (không cần đăng nhập): thấy mức giảm và mã, không thấy lý do nội bộ.
        auth()->logout();
        $this->get($voucher->publicUrl())->assertOk()->assertSee($voucher->code)->assertSee(vnd(20000))->assertDontSee('Sai món');
        $this->get("/{$this->restaurant->slug}/uu-dai/".str_repeat('a', 32))->assertNotFound();
        $this->actingAs($this->owner);

        // Lần sau, bàn khác: nhập mã khách đưa (không phân biệt hoa thường) là áp vào phiếu.
        $this->post("$this->base/tables/{$table->id}/close");
        $this->order('A3', [['item' => $this->tra->id, 'quantity' => 5]]);
        $next = DiningTable::withoutGlobalScopes()->whereNull('closed_at')->sole();
        $this->post("$this->base/tables/{$next->id}/voucher", ['code' => 'XXXXXX'])->assertSessionHasErrorsIn('voucher', 'code');
        $this->post("$this->base/tables/{$next->id}/voucher", ['code' => strtolower($voucher->code)])->assertSessionHas('status', __('dinein.offer_applied'));
        $this->assertSame($next->id, $voucher->fresh()->used_table_id);
        $this->get("$this->base/tables/A3")->assertSee('−'.vnd(20000))->assertSee(vnd(30000));

        // Gắn khách sau đó không làm mất voucher không tên đang áp.
        $this->post("$this->base/tables/{$next->id}/guest", ['name' => 'Lan', 'phone' => '0911000111']);
        $this->assertSame($voucher->id, $next->fresh()->customer_offer_id);

        // Trang Ưu đãi (quản lý): thấy mã đã dùng ở bàn nào; mã đã dùng không huỷ được, mã chưa dùng thì huỷ được.
        // Tra cứu: không tìm thì không liệt kê; tìm theo mã (một phần, chữ thường được) hoặc số điện thoại, lọc trạng thái.
        $this->get("$this->base/offers")->assertOk()->assertSee(__('dinein.offers_search_hint'))->assertDontSee($voucher->code);
        $this->get("$this->base/offers?q=".strtolower(substr($voucher->code, 0, 4)))->assertSee($voucher->code)->assertSee(__('dinein.voucher_used_table', ['table' => 'A3']));
        $this->get("$this->base/offers?q={$voucher->code}&status[]=unused")->assertSee(__('dinein.offers_none'));
        $this->get("$this->base/offers?q={$voucher->code}&status[]=used")->assertSee($voucher->code);
        $this->delete("$this->base/offers/{$voucher->id}")->assertStatus(422);
        $this->post("$this->base/tables/{$next->id}/gift", ['type' => 'percent', 'percent' => 5, 'reason' => 'Khách quen'])->assertSessionHas('voucher');
        $spare = CustomerOffer::withoutGlobalScopes()->latest('id')->first();
        $this->delete("$this->base/offers/{$spare->id}")->assertSessionHas('status');
        $this->assertNull(CustomerOffer::withoutGlobalScopes()->find($spare->id));
        $staff = $this->member($this->restaurant, 'staff');
        $this->actingAs($staff)->get("$this->base/offers")->assertForbidden();

        // Nhân viên được giao quyền Tra cứu ưu đãi: tìm được nhưng không huỷ mã được.
        $named = CustomerOffer::create(['restaurant_id' => $this->restaurant->id, 'customer_id' => Customer::withoutGlobalScopes()->where('phone', '0911000111')->value('id'),
            'type' => DiscountType::Percent, 'value' => 15, 'expires_on' => '2026-11-01']);
        $staff->forceFill(['permissions' => ['dinein.vouchers']])->save();
        $this->actingAs($staff->fresh())->get("$this->base/offers?q=0911 000")->assertOk()->assertSee($named->code)
            ->assertSee(mask_phone('0911000111'))->assertDontSee('0911000111')->assertSee(__('dinein.voucher_expired'))
            ->assertDontSee(__('dinein.voucher_delete'));
        $this->get("$this->base/offers?q=111&status[]=expired")->assertSee($named->code);
        $this->delete("$this->base/offers/{$named->id}")->assertForbidden();
    }

    public function test_incident_without_note_can_gift_an_offer_for_the_guest(): void
    {
        $this->order('A1', [['item' => $this->tra->id, 'quantity' => 1]]);
        $table = DiningTable::withoutGlobalScopes()->sole();
        $tables = "$this->base/tables/{$table->id}";

        // Nội dung sự cố, ghi chú ưu đãi không bắt buộc; hạn dùng mặc định 1 tháng, lịch có nút 1 tháng, 2 tháng.
        $this->get("$this->base/tables/A1")->assertSee('name="gift"', false)->assertSee('data-date-months="2"', false)
            ->assertSee('value="'.today()->addMonthNoOverflow()->toDateString().'"', false)->assertDontSee('data-date-shortcut="0"', false);
        $this->post("$tables/incidents", ['kind' => 'wrong'])->assertSessionHas('status', __('dinein.incident_saved'));
        $this->assertNull(CustomerOffer::withoutGlobalScopes()->first());

        // Ghi sự cố kèm tặng ưu đãi: ưu đãi loại Sự cố, gắn với sự cố, mở mã QR.
        $this->post("$tables/incidents", ['kind' => 'slow', 'note' => 'Chờ 40 phút', 'gift' => '1', 'type' => 'amount', 'thousands' => 30])
            ->assertSessionHas('status', __('dinein.incident_gift_saved'))->assertSessionHas('voucher');
        $offer = CustomerOffer::withoutGlobalScopes()->sole();
        $incident = Incident::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame([CustomerOffer::KIND_INCIDENT, $incident->id, 30000, __('dinein.incident_kinds.slow')], [$offer->kind, $offer->incident_id, $offer->value, $offer->reason]);
        $this->get("$this->base/tables/A1")->assertSee(__('dinein.incident_offer', ['value' => $offer->valueLabel(), 'code' => $offer->code]));
        $this->get("$this->base/incidents")->assertSee($offer->code);
        // Ưu đãi sự cố ghi loại sự cố và nội dung, không ghi "Sự cố", không ghi tên khi không có khách.
        $this->get("$this->base/offers?q={$offer->code}")->assertSee(__('dinein.incident_kinds.slow').' · Chờ 40 phút')
            ->assertDontSee(__('dinein.offer_kinds.incident').' · '.__('dinein.incident_kinds.slow'))->assertDontSee('Không tên');

        // Ưu đãi khách quen không cần ghi chú.
        $this->post("$tables/gift", ['type' => 'percent', 'percent' => 5])->assertSessionHas('voucher');
        $this->assertSame(CustomerOffer::KIND_LOYAL, CustomerOffer::withoutGlobalScopes()->latest('id')->first()->kind);

        // Người không có quyền tặng ưu đãi: ghi sự cố được, phần tặng bị bỏ qua.
        $this->actingAs($this->member($this->restaurant, 'staff'))->post("$tables/incidents", ['kind' => 'other', 'gift' => '1', 'type' => 'percent', 'percent' => 50]);
        $this->assertSame(2, CustomerOffer::withoutGlobalScopes()->count());
    }

    public function test_guest_without_phone(): void
    {
        $this->order('A1', [['item' => $this->tra->id, 'quantity' => 1]]);
        $table = DiningTable::withoutGlobalScopes()->sole();

        // Không có số điện thoại vẫn gắn được; sửa tên không tạo khách mới.
        $this->post("$this->base/tables/{$table->id}/guest", ['name' => 'Anh áo xanh', 'phone' => ''])->assertSessionHasNoErrors();
        $customer = $table->fresh()->customer;
        $this->assertSame(['Anh áo xanh', null], [$customer->name, $customer->phone]);
        $this->post("$this->base/tables/{$table->id}/guest", ['name' => 'Anh Tùng'])->assertSessionHasNoErrors();
        $this->assertSame([$customer->id, 'Anh Tùng'], [$table->fresh()->customer_id, $table->fresh()->customer->name]);
        $this->get("$this->base/tables/A1")->assertSee('Anh Tùng')->assertSee(__('dinein.guest_phone_hint'));
    }

    public function test_items_without_station_skip_the_screens(): void
    {
        // Trà đá chưa chọn khu: gọi xong là đã mang ra, không hiện ở khu nào, không có nhãn trạng thái, đóng bàn được ngay.
        $this->tra->newQuery()->withoutGlobalScopes()->whereKey($this->tra->id)->update(['dinein_station_id' => null]);
        $this->order('A1', [['item' => $this->tra->id, 'quantity' => 2]])->assertSessionHasNoErrors();
        $line = OrderLine::withoutGlobalScopes()->sole();
        $this->assertSame(LineStatus::Served, $line->status);
        $this->assertNull($line->dinein_station_id);

        $this->get("$this->kitchenBase/{$this->bar->id}", ['X-Board' => '1'])->assertDontSee('Trà đá');
        $this->get("$this->kitchenBase/{$this->kitchen->id}", ['X-Board' => '1'])->assertDontSee('Trà đá');
        $this->get("$this->base/tables/A1")->assertOk()->assertSee('Trà đá')->assertDontSee('<span class="badge', false);

        // Gọi nhầm thì người có quyền huỷ món vẫn huỷ được.
        $this->post("$this->base/lines/{$line->id}/cancel")->assertSessionHas('status');
        $this->assertSame(LineStatus::Cancelled, $line->fresh()->status);

        // Đơn mang đi: món không qua khu đã xong ngay, không tính vào "n/m món đã xong".
        $order = $this->takeawayOrder();
        $tea = $order->items()->withoutGlobalScopes()->where('menu_item_id', $this->tra->id)->sole();
        $this->assertNotNull($tea->done_at);
        $this->get("$this->kitchenBase/takeaway")->assertSee(__('kitchen.items_done', ['done' => 0, 'total' => 2]));
    }

    public function test_without_kitchen_module_nothing_waits_for_a_station(): void
    {
        app(ModuleManager::class)->disable($this->restaurant, 'kitchen');

        $this->order('A1', [$this->comTamLine()])->assertSessionHasNoErrors();
        $line = OrderLine::withoutGlobalScopes()->sole();
        $this->assertSame(LineStatus::Served, $line->status, 'Tắt Màn hình khu thì món có khu cũng không chờ.');
        $this->assertNull($line->dinein_station_id);

        $this->get($this->kitchenBase)->assertNotFound();
        $this->get("$this->base/tables")->assertOk()->assertDontSee(__('kitchen.screens'));
        // Khu phục vụ vẫn ở Cài đặt chung (dùng cho Lịch làm việc), nhưng không chọn món cho khu.
        $this->get("$this->base/stations/{$this->bar->id}/edit")->assertOk()->assertDontSee('name="items[]"', false);
    }

    public function test_station_settings_move_items(): void
    {
        $capacity = "/{$this->restaurant->slug}/admin/restaurant/capacity";
        $this->get("$this->base/stations")->assertRedirect($capacity);
        $this->get($capacity)->assertOk()->assertSee(__('dinein.stations'))->assertSee('Quầy nước')
            ->assertDontSee(__('dinein.unassigned', ['count' => 1]));
        $this->get("$this->base/stations/{$this->bar->id}/edit")->assertOk()->assertSee('Cơm tấm');

        $this->put("$this->base/stations/{$this->bar->id}", ['name' => 'Bar', 'items' => [$this->comTam->id]])->assertRedirect($capacity);
        $this->assertSame($this->bar->id, $this->comTam->fresh()->dinein_station_id);
        $this->assertNull($this->tra->fresh()->dinein_station_id, 'Bỏ chọn thì món về chưa chọn khu.');
        $this->get($capacity)->assertSee(__('dinein.unassigned', ['count' => 1]));
        $log = ActivityLog::where('summary', __('dinein.station_items_log', ['name' => 'Bar']))->sole();
        $this->assertSame(2, ActivityLog::where('area', 'dinein')->count(), 'Đổi tên khu cũng ghi nhật ký.');
        $this->assertSame([__('dinein.log_added', ['list' => 'Cơm tấm']), __('dinein.log_removed', ['list' => 'Trà đá'])], $log->changes);

        $this->put("$this->base/stations/{$this->bar->id}", ['name' => 'Bếp'])->assertSessionHasErrors(['name' => __('dinein.station_taken')]);
        $this->post("$this->base/stations", ['name' => 'Bar'])->assertSessionHasErrorsIn('station-dialog', 'name');
        $this->followingRedirects()->from($capacity)->post("$this->base/stations", ['name' => 'Bar'])->assertSee('data-auto-open', false);
        $this->post("$this->base/stations", ['name' => 'Bánh ngọt'])->assertRedirect();

        $this->delete("$this->base/stations/{$this->bar->id}")->assertRedirect($capacity);
        $this->assertNull($this->comTam->fresh()->dinein_station_id);

        // Nhân viên không có quyền cài đặt.
        $this->actingAs($this->member($this->restaurant, 'staff'))->get("$this->base/stations/{$this->kitchen->id}/edit")->assertForbidden();
    }

    public function test_other_restaurants_lines_are_not_reachable(): void
    {
        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'menu', 'dinein');
        app(TenantContext::class)->set($other);
        $table = DiningTable::create(['area_id' => Area::sole()->id, 'table_number' => 1, 'label' => 'A1']);
        $line = $table->lines()->create(['name' => 'Lạ', 'unit_price' => 1, 'quantity' => 1, 'status' => LineStatus::Done]);
        $station = Station::first();
        app(TenantContext::class)->forget();

        $this->post("$this->base/lines/{$line->id}/serve")->assertNotFound();
        $this->post("$this->base/tables/{$table->id}/close")->assertNotFound();
        $this->get("$this->kitchenBase/{$station->id}")->assertNotFound();
        $this->getJson("$this->base/poll")->assertJsonCount(0, 'ready');
    }

    public function test_module_off_hides_everything(): void
    {
        $plain = Restaurant::factory()->create();
        $this->enableModules($plain, 'menu');
        $this->actingAs(User::factory()->owner($plain)->create())
            ->get("/{$plain->slug}/admin/dinein/tables")->assertNotFound();
        $this->get("/{$plain->slug}/admin/menu")->assertDontSee(__('modules.dinein'));
    }
}
