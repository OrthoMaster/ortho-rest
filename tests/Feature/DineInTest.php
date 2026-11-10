<?php

namespace Tests\Feature;

use App\Enums\ShiftType;
use App\Models\ActivityLog;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuItemOption;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Schedule\Duty;
use App\Modules\Schedule\Models\Assignment;
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
        $this->enableModules($this->restaurant, 'menu', 'dinein', 'staff', 'schedule');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->base = "/{$this->restaurant->slug}/admin/dinein";
        $this->actingAs($this->owner);

        app(TenantContext::class)->set($this->restaurant);
        [$this->kitchen, $this->bar] = Station::orderBy('sort_order')->get()->all();
        $this->area = Area::sole();
        $category = MenuCategory::create(['name' => 'Món chính']);
        $this->comTam = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cơm tấm', 'price_dine_in' => 85000]);
        $this->tra = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Trà đá', 'price_dine_in' => 10000, 'dinein_station_id' => $this->bar->id]);

        $meat = $this->comTam->optionGroups()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Thịt', 'is_required' => true]);
        $sauce = $this->comTam->optionGroups()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Nước chấm', 'is_multiple' => true]);
        foreach ([[$meat, 'Sườn', 0], [$meat, 'Sườn cọng', 15000], [$sauce, 'Không ớt', 0], [$sauce, 'Nhiều tỏi', 2000]] as [$group, $name, $price]) {
            $this->options[$name] = $group->options()->create(['restaurant_id' => $this->restaurant->id, 'name' => $name, 'price' => $price]);
        }
        app(TenantContext::class)->forget();
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

        foreach (['dinein.order', 'dinein.close', 'dinein.cancel', 'dinein.stations', 'dinein.settings'] as $key) {
            $this->assertTrue($manager->can($key), $key);
        }
        $this->assertTrue($staff->can('dinein.order'));
        $this->assertTrue($staff->can('dinein.close'));
        $this->assertFalse($staff->can('dinein.cancel'));
        $this->assertFalse($staff->can('dinein.stations'));

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
        $this->actingAs($this->owner)->get("$this->base/screens/{$this->kitchen->id}")->assertOk()->assertSee('Cơm tấm')->assertDontSee('Trà đá');
        $this->get("$this->base/screens/{$this->kitchen->id}", ['X-Board' => '1'])->assertOk()->assertDontSee('<html', false)->assertSee('data-line-id="'.$rice->id.'"', false);

        $this->actingAs($waiter)->getJson("$this->base/poll")->assertOk()->assertJsonCount(0, 'ready');

        // Không đóng được khi còn món chưa mang ra.
        $table = DiningTable::withoutGlobalScopes()->sole();
        $this->post("$this->base/tables/{$table->id}/close")->assertSessionHas('error', __('dinein.close_pending'));

        $this->actingAs($this->owner)->post("$this->base/screens/{$this->kitchen->id}/done", ['lines' => [$rice->id, $tea->id]])
            ->assertSessionHas('status', trans_choice('dinein.done_count', 1, ['count' => 1]));
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
        $this->actingAs($cook)->get("$this->base/screens")->assertForbidden();
        $this->assertFalse(Duty::canOpenStation($cook, $this->kitchen->id));

        // Ca làm 10:00–14:00 thứ Hai; bây giờ 12:00 thứ Hai.
        $shift = Shift::create(['restaurant_id' => $this->restaurant->id, 'type' => ShiftType::Work, 'weekday' => 1, 'name' => 'Ca trưa', 'start_time' => '10:00', 'end_time' => '14:00']);

        $schedule = "/{$this->restaurant->slug}/admin/schedule/assignments";
        $this->actingAs($this->owner)->postJson($schedule, ['user' => $cook->id, 'date' => '2026-11-09', 'slot' => (string) $shift->id, 'lane' => 's'.$this->kitchen->id])->assertOk();
        $this->postJson($schedule, ['user' => $this->owner->id, 'date' => '2026-11-09', 'slot' => (string) $shift->id, 'lane' => 'a'.$this->area->id])->assertOk();
        $this->assertSame(2, Assignment::withoutGlobalScopes()->count());

        $this->actingAs($cook)->get("$this->base/screens")->assertRedirect("$this->base/screens/{$this->kitchen->id}");
        $this->get("$this->base/screens/{$this->kitchen->id}")->assertOk();
        $this->get("$this->base/screens/{$this->bar->id}")->assertForbidden();
        $this->get($this->base)->assertRedirect("$this->base/tables");

        $this->actingAs($this->owner)->order('A2', [$this->comTamLine()]);
        $this->actingAs($cook)->getJson("$this->base/poll")->assertJsonPath('stations.0.id', $this->kitchen->id)->assertJsonCount(1, 'stations.0.ids');

        // Hết ca thì không còn ở bếp.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00'));
        $this->get("$this->base/screens/{$this->kitchen->id}")->assertForbidden();
        $this->getJson("$this->base/poll")->assertJsonCount(0, 'stations');

        // Tắt Lịch làm việc thì phân công không còn tác dụng.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00'));
        $this->get("$this->base/screens/{$this->kitchen->id}")->assertOk();
        app(ModuleManager::class)->disable($this->restaurant, 'schedule');
        $this->get("$this->base/screens/{$this->kitchen->id}")->assertForbidden();
    }

    public function test_shift_crossing_midnight_counts_for_previous_day(): void
    {
        $shift = Shift::create(['restaurant_id' => $this->restaurant->id, 'type' => ShiftType::Work, 'weekday' => 1, 'name' => 'Ca khuya', 'start_time' => '18:00', 'end_time' => '02:00']);
        Assignment::create(['restaurant_id' => $this->restaurant->id, 'work_date' => '2026-11-09', 'shift_id' => $shift->id, 'dinein_station_id' => $this->bar->id, 'user_id' => $this->owner->id]);

        app(TenantContext::class)->set($this->restaurant);
        $this->assertSame([], Duty::stationIds($this->owner), 'Chưa đến ca.');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 19:00'));
        $this->assertSame([$this->bar->id], Duty::stationIds($this->owner));
        $this->travelTo(CarbonImmutable::parse('2026-11-10 01:30'));
        $this->assertSame([$this->bar->id], Duty::stationIds($this->owner));
        $this->travelTo(CarbonImmutable::parse('2026-11-10 02:30'));
        $this->assertSame([], Duty::stationIds($this->owner));
    }

    public function test_station_settings_move_items(): void
    {
        $capacity = "/{$this->restaurant->slug}/admin/restaurant/capacity";
        $this->get("$this->base/stations")->assertRedirect($capacity);
        $this->get($capacity)->assertOk()->assertSee(__('dinein.stations'))->assertSee('Quầy nước')
            ->assertSee(__('dinein.unassigned', ['count' => 1, 'name' => 'Bếp']));
        $this->get("$this->base/stations/{$this->bar->id}/edit")->assertOk()->assertSee('Cơm tấm');

        $this->put("$this->base/stations/{$this->bar->id}", ['name' => 'Bar', 'items' => [$this->comTam->id]])->assertRedirect($capacity);
        $this->assertSame($this->bar->id, $this->comTam->fresh()->dinein_station_id);
        $this->assertNull($this->tra->fresh()->dinein_station_id, 'Bỏ chọn thì món về chưa chọn khu.');
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
        $this->get("$this->base/screens/{$station->id}")->assertNotFound();
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
