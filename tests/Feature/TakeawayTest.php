<?php

namespace Tests\Feature;

use App\Models\CustomerOffer;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\ModuleManager;
use App\Modules\Takeaway\DiscountType;
use App\Modules\Takeaway\Models\DiscountCode;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\Models\TakeawayOrderItem;
use App\Modules\Takeaway\OrderStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TakeawayTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private MenuItem $pho;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-12-01 09:00'));

        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'menu', 'takeaway');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->base = "/{$this->restaurant->slug}/admin/takeaway";
        $this->pho = $this->item($this->restaurant, 'Phở', 70000);

        $this->actingAs($this->owner);
    }

    private function item(Restaurant $restaurant, string $name, ?int $price, array $attributes = []): MenuItem
    {
        $category = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Món']);

        return MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => $name,
            'price_dine_in' => 90000,
            'price_takeaway' => $price,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<int, int>  $items  id món => số lượng
     * @return array<string, mixed>
     */
    private function form(array $items, array $overrides = []): array
    {
        return [
            'customer_name' => 'Anh Tuấn',
            'customer_phone' => '0987654321',
            'pickup_date' => '2026-12-01',
            'pickup_time' => '12:00',
            'lines' => collect($items)->map(fn (int $quantity, int $id) => ['item' => $id, 'quantity' => $quantity])->values()->all(),
            ...$overrides,
        ];
    }

    /** Sửa đơn: món đã có trên đơn gửi kèm id dòng, như form gửi. */
    private function editForm(TakeawayOrder $order, array $items, array $overrides = []): array
    {
        $form = $this->form($items, $overrides);
        $existing = TakeawayOrderItem::withoutGlobalScopes()->where('takeaway_order_id', $order->id)->pluck('id', 'menu_item_id');

        foreach ($form['lines'] as &$line) {
            if ($existing->has($line['item'])) {
                $line['line'] = $existing[$line['item']];
            }
        }

        return $form;
    }

    private function order(): TakeawayOrder
    {
        $this->post($this->base, $this->form([$this->pho->id => 2]))->assertSessionHasNoErrors();

        return TakeawayOrder::forRestaurant($this->restaurant)->latest('id')->first();
    }

    public function test_order_uses_the_takeaway_price_and_computes_the_total(): void
    {
        $order = $this->order();

        $this->assertSame(140000, $order->total);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame($this->owner->id, $order->created_by);
        $this->assertSame(1, $order->number);

        $this->get("$this->base/{$order->id}")->assertOk()->assertSee('2 × Phở')->assertSee('140.000 ₫');
    }

    public function test_order_keeps_its_price_after_the_menu_changes(): void
    {
        $order = $this->order();

        $this->pho->newQuery()->withoutGlobalScopes()->whereKey($this->pho->id)->update(['price_takeaway' => 99000, 'name' => 'Đổi tên']);

        $this->get("$this->base/{$order->id}")->assertSee('2 × Phở')->assertSee('140.000 ₫');
    }

    public function test_numbers_are_per_restaurant_and_increase(): void
    {
        $this->assertSame(1, $this->order()->number);
        $this->assertSame(2, $this->order()->number);

        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'menu', 'takeaway');
        $otherItem = $this->item($other, 'Bún', 50000);

        $this->actingAs(User::factory()->owner($other)->create())
            ->post("/{$other->slug}/admin/takeaway", $this->form([$otherItem->id => 1]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TakeawayOrder::forRestaurant($other)->sole()->number);
    }

    public function test_unavailable_items_are_refused(): void
    {
        $soldOut = $this->item($this->restaurant, 'Hết', 10000, ['is_sold_out' => true]);
        $hidden = $this->item($this->restaurant, 'Ẩn', 10000, ['is_visible' => false]);
        $dineInOnly = $this->item($this->restaurant, 'Tại quán', null);

        foreach ([$soldOut, $hidden, $dineInOnly] as $item) {
            $this->post($this->base, $this->form([$item->id => 1]))->assertSessionHasErrors('lines');
        }

        $this->assertSame(0, TakeawayOrder::forRestaurant($this->restaurant)->count());
    }

    public function test_item_of_another_restaurant_is_refused(): void
    {
        $other = Restaurant::factory()->create();
        $otherItem = $this->item($other, 'Của quán khác', 1000);

        $this->post($this->base, $this->form([$otherItem->id => 1]))->assertSessionHasErrors('lines');
    }

    public function test_empty_order_is_refused(): void
    {
        $this->post($this->base, $this->form([$this->pho->id => 0]))->assertSessionHasErrors('lines');
    }

    public function test_pickup_must_be_within_pickup_hours(): void
    {
        $this->post($this->base, $this->form([$this->pho->id => 1], ['pickup_time' => '22:30']))
            ->assertSessionHasErrors('pickup_at');
    }

    public function test_status_path_and_wrong_jumps(): void
    {
        $order = $this->order();
        $url = "$this->base/{$order->id}/status";

        $this->post($url, ['status' => 'delivered'])->assertSessionHasErrors('status');

        foreach (['preparing', 'ready', 'delivered'] as $status) {
            $this->post($url, ['status' => $status])->assertSessionHasNoErrors();
        }

        $this->post($url, ['status' => 'cancelled'])->assertSessionHasErrors('status');
    }

    public function test_cash_register_bill_number_on_delivery_and_later(): void
    {
        $order = $this->order();
        $url = "$this->base/{$order->id}/status";

        foreach (['preparing', 'ready'] as $status) {
            $this->post($url, ['status' => $status]);
        }

        // Giao khách: hộp thoại có ô số hoá đơn (không bắt buộc).
        $this->get("$this->base/{$order->id}")->assertSee('data-open-dialog="deliver-'.$order->id.'"', false)->assertSee('name="bill_number"', false);
        $this->post($url, ['status' => 'delivered', 'bill_number' => '  000123 '])->assertSessionHasNoErrors();
        $this->assertSame('000123', $order->fresh()->bill_number);
        $this->get("$this->base?date={$order->pickup_at->toDateString()}")->assertSee(__('dinein.bills_number', ['number' => '000123']));

        // Sửa sau khi giao (nhập sai, quên nhập); để trống là xoá.
        $this->put("$this->base/{$order->id}/bill-number", ['bill_number' => '000124'])->assertSessionHas('status');
        $this->assertSame('000124', $order->fresh()->bill_number);
        $this->put("$this->base/{$order->id}/bill-number", ['bill_number' => ''])->assertSessionHas('status');
        $this->assertNull($order->fresh()->bill_number);
        $this->put("$this->base/{$this->order()->id}/bill-number", ['bill_number' => '1'])->assertNotFound();
    }

    public function test_order_form_lists_takeaway_items_and_marks_sold_out(): void
    {
        $this->item($this->restaurant, 'Đã hết', 10000, ['is_sold_out' => true]);
        $this->item($this->restaurant, 'Chỉ tại quán', null);
        $this->item($this->restaurant, 'Đang ẩn món', 10000, ['is_visible' => false]);

        $this->get("$this->base/create")
            ->assertOk()
            ->assertSee('data-dine-pick="'.$this->pho->id.'"', false)
            ->assertSee('Đã hết')
            ->assertSee(__('menu.sold_out'))
            ->assertDontSee('Chỉ tại quán')
            ->assertDontSee('Đang ẩn món');
    }

    private function code(string $code, DiscountType $type, int $value, bool $active = true, ?Restaurant $restaurant = null): DiscountCode
    {
        return DiscountCode::create([
            'restaurant_id' => ($restaurant ?? $this->restaurant)->id,
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'is_active' => $active,
        ]);
    }

    private function discounted(array $discount): TakeawayOrder
    {
        $this->post($this->base, $this->form([$this->pho->id => 2], ['discount_enabled' => 1, ...$discount]))->assertSessionHasNoErrors();

        return TakeawayOrder::forRestaurant($this->restaurant)->latest('id')->first();
    }

    public function test_order_without_discount_keeps_subtotal_equal_to_total(): void
    {
        $order = $this->order();

        $this->assertSame(140000, $order->subtotal);
        $this->assertSame(0, $order->discount_amount);
        $this->assertSame(140000, $order->total);
    }

    public function test_direct_percent_discount(): void
    {
        $order = $this->discounted(['discount_mode' => 'percent', 'discount_percent' => 10]);

        $this->assertSame(14000, $order->discount_amount);
        $this->assertSame(126000, $order->total);
        $this->assertSame(DiscountType::Percent, $order->discount_type);
    }

    public function test_direct_amount_discount_cannot_exceed_the_subtotal(): void
    {
        $this->assertSame(120000, $this->discounted(['discount_mode' => 'amount', 'discount_thousands' => 20])->total);
        $this->assertSame(0, $this->discounted(['discount_mode' => 'amount', 'discount_thousands' => 999])->total);
    }

    public function test_code_discount_is_case_insensitive_and_recorded(): void
    {
        $this->code('GIAM10', DiscountType::Percent, 10);

        $order = $this->discounted(['discount_mode' => 'code', 'discount_code' => ' giam10 ']);

        $this->assertSame('GIAM10', $order->discount_code);
        $this->assertSame(126000, $order->total);

        $this->get("$this->base/{$order->id}")->assertSee('GIAM10')->assertSee('14.000 ₫')->assertSee('126.000 ₫');
    }

    public function test_order_keeps_its_discount_after_the_code_changes(): void
    {
        $code = $this->code('GIAM10', DiscountType::Percent, 10);
        $order = $this->discounted(['discount_mode' => 'code', 'discount_code' => 'GIAM10']);

        $this->delete("$this->base/discounts/{$code->id}");

        $this->assertSame(126000, $order->fresh()->total);
        $this->get("$this->base/{$order->id}")->assertOk()->assertSee('GIAM10');
    }

    public function test_wrong_inactive_and_foreign_codes_are_refused(): void
    {
        $this->code('TAT', DiscountType::Percent, 10, active: false);
        $this->code('QUANKHAC', DiscountType::Percent, 10, restaurant: Restaurant::factory()->create());

        foreach (['KHONGCO', 'TAT', 'QUANKHAC'] as $code) {
            $this->post($this->base, $this->form([$this->pho->id => 1], ['discount_enabled' => 1, 'discount_mode' => 'code', 'discount_code' => $code]))
                ->assertSessionHasErrors('discount_code');

            $this->getJson("$this->base/discounts/check?code=$code")->assertJson(['valid' => false]);
        }

        $this->assertSame(0, TakeawayOrder::forRestaurant($this->restaurant)->count());
    }

    public function test_discount_values_are_validated(): void
    {
        $items = [$this->pho->id => 1];
        $on = ['discount_enabled' => 1];

        $this->post($this->base, $this->form($items, [...$on, 'discount_mode' => 'percent', 'discount_percent' => 101]))->assertSessionHasErrors('discount_percent');
        $this->post($this->base, $this->form($items, [...$on, 'discount_mode' => 'percent']))->assertSessionHasErrors('discount_percent');
        $this->post($this->base, $this->form($items, [...$on, 'discount_mode' => 'amount']))->assertSessionHasErrors('discount_thousands');
        $this->post($this->base, $this->form($items, [...$on, 'discount_mode' => 'code']))->assertSessionHasErrors('discount_code');
    }

    public function test_values_of_other_modes_are_ignored(): void
    {
        $order = $this->discounted(['discount_mode' => 'amount', 'discount_thousands' => 10, 'discount_percent' => 50]);

        $this->assertSame(130000, $order->total);
    }

    public function test_discount_is_ignored_when_the_toggle_is_off(): void
    {
        $this->post($this->base, $this->form([$this->pho->id => 2], [
            'discount_mode' => 'code', 'discount_percent' => 5, 'discount_thousands' => 10,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(140000, TakeawayOrder::forRestaurant($this->restaurant)->sole()->total);
    }

    public function test_form_has_the_discount_defaults(): void
    {
        $this->get("$this->base/create")
            ->assertSee('name="discount_percent" value="5"', false)
            ->assertSee('name="discount_thousands" value="10"', false);
    }

    public function test_next_visit_voucher_is_used_once_and_returned_when_removed_or_cancelled(): void
    {
        $voucher = CustomerOffer::create(['restaurant_id' => $this->restaurant->id, 'type' => DiscountType::Percent, 'value' => 10, 'reason' => 'Món ra chậm']);
        $this->getJson("$this->base/discounts/check?code=".strtolower($voucher->code))->assertJson(['valid' => true, 'type' => 'percent', 'value' => 10]);

        // Dùng cho đơn mang đi: giảm và đánh dấu đã dùng, đơn khác không dùng lại được.
        $order = $this->discounted(['discount_mode' => 'code', 'discount_code' => strtolower($voucher->code)]);
        $this->assertSame([126000, $voucher->id], [$order->total, $order->customer_offer_id]);
        $this->assertSame($order->id, $voucher->fresh()->used_takeaway_order_id);
        $this->post($this->base, $this->form([$this->pho->id => 1], ['discount_enabled' => 1, 'discount_mode' => 'code', 'discount_code' => $voucher->code]))
            ->assertSessionHasErrors('discount_code');
        $this->getJson("$this->base/discounts/check?code={$voucher->code}")->assertJson(['valid' => false]);

        // Sửa đơn giữ mã thì vẫn giữ; bỏ giảm giá thì mã được trả lại.
        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3], ['discount_enabled' => 1, 'discount_mode' => 'code', 'discount_code' => $voucher->code]))->assertSessionHasNoErrors();
        $this->assertSame(189000, $order->fresh()->total);
        $this->assertNotNull($voucher->fresh()->used_at);
        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3]))->assertSessionHasNoErrors();
        $this->assertNull($voucher->fresh()->used_at);

        // Dùng lại cho đơn mới, rồi huỷ đơn: mã trả lại cho khách.
        $again = $this->discounted(['discount_mode' => 'code', 'discount_code' => $voucher->code]);
        $this->assertNotNull($voucher->fresh()->used_at);
        $this->post("$this->base/{$again->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertNull($voucher->fresh()->used_at);
    }

    public function test_check_endpoint_describes_a_valid_code(): void
    {
        $this->code('BOT20K', DiscountType::Amount, 20000);

        $this->getJson("$this->base/discounts/check?code=bot20k")
            ->assertJson(['valid' => true, 'type' => 'amount', 'value' => 20000]);
    }

    public function test_discount_code_management(): void
    {
        $this->get("$this->base/discounts")->assertOk();
        $this->get("$this->base/discounts/create")->assertOk();

        $this->post("$this->base/discounts", ['code' => 'giam-10', 'type' => 'percent', 'value' => 10, 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $code = DiscountCode::forRestaurant($this->restaurant)->sole();
        $this->assertSame('GIAM-10', $code->code);

        $this->post("$this->base/discounts", ['code' => 'GIAM-10', 'type' => 'percent', 'value' => 5])->assertSessionHasErrors('code');
        $this->post("$this->base/discounts", ['code' => 'co dau', 'type' => 'percent', 'value' => 5])->assertSessionHasErrors('code');
        $this->post("$this->base/discounts", ['code' => 'NHIEU', 'type' => 'percent', 'value' => 101])->assertSessionHasErrors('value');

        $this->put("$this->base/discounts/{$code->id}", ['code' => 'GIAM-10', 'type' => 'amount', 'value' => 5000])->assertSessionHasNoErrors();

        $fresh = $code->fresh();
        $this->assertSame(DiscountType::Amount, $fresh->type);
        $this->assertFalse($fresh->is_active);
    }

    public function test_staff_can_use_codes_but_not_manage_them(): void
    {
        $this->code('GIAM10', DiscountType::Percent, 10);
        $staff = $this->member($this->restaurant, 'staff');

        $this->actingAs($staff)->getJson("$this->base/discounts/check?code=GIAM10")->assertJson(['valid' => true]);
        $this->actingAs($staff)->get("$this->base/discounts")->assertForbidden();
    }

    public function test_another_restaurants_code_cannot_be_reached(): void
    {
        $other = $this->code('QUANKHAC', DiscountType::Percent, 10, restaurant: Restaurant::factory()->create());

        $this->get("$this->base/discounts/{$other->id}/edit")->assertNotFound();
        $this->delete("$this->base/discounts/{$other->id}")->assertNotFound();
    }

    public function test_pickup_defaults_to_today_and_the_current_time_rounded_to_ten_minutes(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 12:04'));

        $this->get("$this->base/create")
            ->assertSee('name="pickup_date" value="2026-12-01"', false)
            ->assertSee('name="pickup_time" value="12:10"', false)
            ->assertSee('data-min="2026-12-01"', false);
    }

    public function test_pickup_default_stays_on_the_current_time_when_it_is_already_on_a_step(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 12:20:00'));

        $this->get("$this->base/create")->assertSee('name="pickup_time" value="12:20"', false);
    }

    public function test_pickup_default_rolls_over_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 23:57'));

        $this->get("$this->base/create")
            ->assertSee('name="pickup_date" value="2026-12-02"', false)
            ->assertSee('name="pickup_time" value="00:00"', false);
    }

    public function test_pickup_in_the_past_is_refused(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 15:00'));

        $this->post($this->base, $this->form([$this->pho->id => 1], ['pickup_time' => '12:00']))
            ->assertSessionHasErrors('pickup_at');
    }

    private function asRole(string $preset): User
    {
        return $this->member($this->restaurant, $preset);
    }

    public function test_direct_discount_follows_positions(): void
    {
        $staff = $this->asRole('staff');
        $this->assertFalse($staff->can('takeaway.direct-discount'));

        $this->actingInTenant($this->restaurant);
        $position = $staff->positions()->first();
        $position->update(['permissions' => [...$position->permissions, 'takeaway.direct-discount']]);
        $staff->forgetPermissions();
        $this->assertTrue($staff->can('takeaway.direct-discount'));
    }

    public function test_direct_discount_defaults_to_owner_and_manager(): void
    {
        $this->assertTrue($this->owner->can('takeaway.direct-discount'));
        $this->assertTrue($this->asRole('manager')->can('takeaway.direct-discount'));
        $this->assertFalse($this->asRole('staff')->can('takeaway.direct-discount'));
    }

    public function test_user_without_the_permission_can_use_codes_but_not_direct_discounts(): void
    {
        $this->code('GIAM10', DiscountType::Percent, 10);
        $staff = $this->asRole('staff');
        $on = ['discount_enabled' => 1];

        $this->actingAs($staff)->get("$this->base/create")
            ->assertSee('name="discount_code"', false)
            ->assertDontSee('name="discount_percent"', false)
            ->assertDontSee('name="discount_thousands"', false);

        $this->post($this->base, $this->form([$this->pho->id => 1], [...$on, 'discount_mode' => 'percent', 'discount_percent' => 50]))->assertForbidden();
        $this->post($this->base, $this->form([$this->pho->id => 1], [...$on, 'discount_mode' => 'amount', 'discount_thousands' => 50]))->assertForbidden();

        $this->post($this->base, $this->form([$this->pho->id => 2], [...$on, 'discount_mode' => 'code', 'discount_code' => 'GIAM10']))
            ->assertSessionHasNoErrors();

        $this->assertSame(126000, TakeawayOrder::forRestaurant($this->restaurant)->sole()->total);
    }

    private function pickup(string $date): TakeawayOrder
    {
        $this->post($this->base, $this->form([$this->pho->id => 1], ['pickup_date' => $date]))->assertSessionHasNoErrors();

        return TakeawayOrder::forRestaurant($this->restaurant)->latest('id')->first();
    }

    public function test_order_code_has_a_letter_three_digits_and_the_pickup_day(): void
    {
        $order = $this->pickup('2026-12-06');

        $this->assertSame('M001_06', $order->code());

        $order->number = 1000;
        $this->assertSame('M1000_06', $order->code());
    }

    public function test_numbers_count_per_pickup_day_not_per_order_day(): void
    {
        $this->assertSame('M001_06', $this->pickup('2026-12-06')->code());
        $this->assertSame('M001_07', $this->pickup('2026-12-07')->code());
        $this->assertSame('M002_06', $this->pickup('2026-12-06')->code());

        $this->travelTo(Carbon::parse('2026-12-05 09:00'));

        $this->assertSame('M003_06', $this->pickup('2026-12-06')->code());
    }

    public function test_moving_the_pickup_day_gives_a_new_number_in_that_day(): void
    {
        $order = $this->pickup('2026-12-06');
        $this->pickup('2026-12-07');

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 1], ['pickup_date' => '2026-12-07']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('takeaway.updated_code', ['code' => 'M002_07']));

        $this->assertSame('M002_07', $order->fresh()->code());

        $this->actingInTenant($this->restaurant);
        $this->assertContains('Mã đơn: M001_06 → M002_07', $order->edits()->sole()->changes);
    }

    public function test_changing_only_the_pickup_time_keeps_the_code(): void
    {
        $order = $this->pickup('2026-12-06');

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 1], ['pickup_date' => '2026-12-06', 'pickup_time' => '13:00']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('takeaway.updated'));

        $this->assertSame('M001_06', $order->fresh()->code());
    }

    public function test_owner_sets_the_code_letter_for_new_orders_only(): void
    {
        $old = $this->pickup('2026-12-06');
        $settings = ['pickup_opens' => '10:00', 'pickup_closes' => '21:00'];

        $this->put("$this->base/settings", [...$settings, 'order_code_prefix' => 't'])->assertSessionHasNoErrors();

        $this->assertSame('T002_06', $this->pickup('2026-12-06')->code());
        $this->assertSame('M001_06', $old->fresh()->code());
    }

    public function test_code_letter_must_be_one_plain_letter(): void
    {
        $settings = ['pickup_opens' => '10:00', 'pickup_closes' => '21:00'];

        foreach (['', 'AB', '1', 'Đ', '_'] as $prefix) {
            $this->put("$this->base/settings", [...$settings, 'order_code_prefix' => $prefix])->assertSessionHasErrors('order_code_prefix');
        }
    }

    public function test_only_the_owner_sets_the_code_letter(): void
    {
        $manager = $this->asRole('manager');

        $this->actingAs($manager)->get("$this->base/settings")->assertDontSee('order_code_prefix', false);

        $this->put("$this->base/settings", ['pickup_opens' => '10:00', 'pickup_closes' => '21:00', 'order_code_prefix' => 'Z'])->assertSessionHasNoErrors();

        $this->assertSame('M', app(ModuleManager::class)->settings($this->restaurant, 'takeaway')['order_code_prefix']);
    }

    public function test_code_is_shown_on_the_list_and_the_order(): void
    {
        $order = $this->order();

        $this->get($this->base)->assertSee('Đơn '.$order->code());
        $this->get("$this->base/{$order->id}")->assertSee('Đơn '.$order->code());
    }

    public function test_changing_items_keeps_old_prices_and_uses_current_price_for_new_items(): void
    {
        $order = $this->order();
        $this->pho->newQuery()->withoutGlobalScopes()->whereKey($this->pho->id)->update(['price_takeaway' => 99000]);
        $tra = $this->item($this->restaurant, 'Trà', 10000);

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3, $tra->id => 1]))
            ->assertSessionHasNoErrors();

        $fresh = $order->fresh();

        $this->assertSame(220000, $fresh->total);
        $this->assertSame($order->code(), $fresh->code());
    }

    public function test_edit_is_recorded_with_who_and_what(): void
    {
        $order = $this->order();
        $tra = $this->item($this->restaurant, 'Trà', 10000);

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$tra->id => 1], ['pickup_time' => '13:00', 'notes' => 'Ít đá']))
            ->assertSessionHasNoErrors();

        $this->actingInTenant($this->restaurant);
        $edit = $order->edits()->sole();

        $this->assertSame($this->owner->id, $edit->user_id);
        $this->assertContains('Bỏ Phở', $edit->changes);
        $this->assertContains('Thêm 1 × Trà', $edit->changes);
        $this->assertContains('Đổi ghi chú', $edit->changes);

        $this->get($this->base)->assertSee(__('takeaway.edited'));
        $this->get("$this->base/{$order->id}")->assertSee(__('takeaway.edit_history'))->assertSee('Bỏ Phở');
    }

    public function test_saving_without_changes_records_nothing(): void
    {
        $order = $this->order();

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 2]))->assertSessionHasNoErrors();

        $this->actingInTenant($this->restaurant);
        $this->assertSame(0, $order->edits()->count());
    }

    public function test_status_does_not_change_when_editing(): void
    {
        $order = $this->order();
        $this->post("$this->base/{$order->id}/status", ['status' => 'preparing']);

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3]))->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_delivered_and_cancelled_orders_cannot_be_edited(): void
    {
        $order = $this->order();
        $this->post("$this->base/{$order->id}/status", ['status' => 'cancelled']);

        $this->get("$this->base/{$order->id}")->assertDontSee(__('takeaway.edit'));
        $this->get("$this->base/{$order->id}/edit")->assertRedirect("$this->base/{$order->id}");
        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3]))->assertSessionHasErrors('lines');

        $this->assertSame(140000, $order->fresh()->total);
    }

    public function test_item_now_sold_out_stays_on_the_order(): void
    {
        $order = $this->order();
        $this->pho->newQuery()->withoutGlobalScopes()->whereKey($this->pho->id)->update(['is_sold_out' => true, 'is_visible' => false]);

        $this->get("$this->base/{$order->id}/edit")->assertOk()->assertSee('"item":'.$this->pho->id.',"quantity":2', false);

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 1]))->assertSessionHasNoErrors();

        $this->assertSame(70000, $order->fresh()->total);
    }

    public function test_options_and_notes_like_ordering_at_the_table(): void
    {
        $comTam = $this->item($this->restaurant, 'Cơm tấm', 50000);
        $meat = $comTam->optionGroups()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Thịt', 'is_required' => true]);
        $sauce = $comTam->optionGroups()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Nước chấm', 'is_multiple' => true]);
        $suon = $meat->options()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Sườn', 'price' => 0]);
        $cong = $meat->options()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Sườn cọng', 'price' => 15000]);
        $ot = $sauce->options()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Không ớt', 'price' => 0]);

        // Nhóm bắt buộc phải chọn, nhóm chọn một không chọn hai.
        $line = fn (array $options, int $quantity = 1, ?string $note = null) => ['item' => $comTam->id, 'quantity' => $quantity, 'options' => $options, 'note' => $note];
        $this->post($this->base, $this->form([], ['lines' => [$line([$ot->id])]]))->assertSessionHasErrors(['lines' => __('takeaway.option_required', ['name' => 'Cơm tấm', 'group' => 'Thịt'])]);
        $this->post($this->base, $this->form([], ['lines' => [$line([$suon->id, $cong->id])]]))->assertSessionHasErrors('lines');

        // Cùng món, khác tuỳ chọn là hai dòng; giá gồm tiền cộng thêm.
        $this->post($this->base, $this->form([], ['lines' => [$line([$cong->id, $ot->id], 2, 'Ít cơm'), $line([$suon->id])]]))->assertSessionHasNoErrors();
        $order = TakeawayOrder::forRestaurant($this->restaurant)->latest('id')->first();
        $this->assertSame(2 * 65000 + 50000, $order->total);

        $this->get("$this->base/{$order->id}")->assertSee('Thịt: Sườn cọng')->assertSee('Nước chấm: Không ớt')->assertSee('Ít cơm');

        // Sửa: dòng giữ tuỳ chọn thì giữ giá cũ dù menu đổi giá; đổi tuỳ chọn là món mới, giá hiện tại.
        $comTam->newQuery()->withoutGlobalScopes()->whereKey($comTam->id)->update(['price_takeaway' => 60000]);
        $rows = TakeawayOrderItem::withoutGlobalScopes()->where('takeaway_order_id', $order->id)->orderBy('id')->get();
        $this->get("$this->base/{$order->id}/edit")->assertOk()->assertSee('"line":'.$rows[0]->id, false);

        $this->put("$this->base/{$order->id}", $this->form([], ['lines' => [
            [...$line([$cong->id, $ot->id], 3, 'Ít cơm'), 'line' => $rows[0]->id],
            [...$line([$cong->id]), 'line' => $rows[1]->id],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame(3 * 65000 + 75000, $order->fresh()->total);
        $this->actingInTenant($this->restaurant);
        $changes = $order->edits()->sole()->changes;
        $this->assertContains('Cơm tấm (Thịt: Sườn cọng; Nước chấm: Không ớt): 2 → 3 phần', $changes);
        $this->assertContains('Bỏ Cơm tấm (Thịt: Sườn)', $changes);
        $this->assertContains('Thêm 1 × Cơm tấm (Thịt: Sườn cọng)', $changes);
    }

    public function test_phone_is_optional_but_must_be_valid(): void
    {
        $this->post($this->base, $this->form([$this->pho->id => 1], ['customer_phone' => '12ab']))->assertSessionHasErrors('customer_phone');
        $this->post($this->base, $this->form([$this->pho->id => 1], ['customer_phone' => '']))->assertSessionHasNoErrors();

        $order = TakeawayOrder::forRestaurant($this->restaurant)->latest('id')->first();
        $this->assertNull($order->customer->phone);
        $this->get("$this->base/{$order->id}")->assertOk()->assertDontSee('tel:', false);

        // Sửa đơn khách không số: giữ khách đó, không tạo khách mới mỗi lần lưu.
        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 1], ['customer_phone' => '']))->assertSessionHasNoErrors();
        $this->assertSame($order->customer_id, $order->fresh()->customer_id);
        $this->actingInTenant($this->restaurant);
        $this->assertSame(0, $order->edits()->count());
    }

    public function test_new_item_must_be_available(): void
    {
        $order = $this->order();
        $soldOut = $this->item($this->restaurant, 'Hết', 10000, ['is_sold_out' => true]);

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 2, $soldOut->id => 1]))->assertSessionHasErrors('lines');
    }

    public function test_edit_keeps_a_code_that_was_turned_off_and_recalculates_it(): void
    {
        $code = $this->code('GIAM10', DiscountType::Percent, 10);
        $order = $this->discounted(['discount_mode' => 'code', 'discount_code' => 'GIAM10']);
        $code->update(['is_active' => false, 'value' => 50]);

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 4], [
            'discount_enabled' => 1, 'discount_mode' => 'code', 'discount_code' => 'GIAM10',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(252000, $order->fresh()->total);
    }

    public function test_staff_keeps_a_direct_discount_but_cannot_change_it(): void
    {
        $order = $this->discounted(['discount_mode' => 'percent', 'discount_percent' => 10]);
        $staff = $this->asRole('staff');
        $keep = ['discount_enabled' => 1, 'discount_mode' => 'percent', 'discount_percent' => 10];

        $this->actingAs($staff)->get("$this->base/{$order->id}/edit")->assertSee('Đơn đang giảm 10%');

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3], $keep))->assertSessionHasNoErrors();
        $this->assertSame(189000, $order->fresh()->total);

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3], [...$keep, 'discount_percent' => 50]))->assertForbidden();

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3]))->assertSessionHasNoErrors();
        $this->assertSame(210000, $order->fresh()->total);
    }

    public function test_form_of_staff_sends_only_the_field_of_the_kept_discount(): void
    {
        $percent = $this->discounted(['discount_mode' => 'percent', 'discount_percent' => 10]);
        $amount = $this->discounted(['discount_mode' => 'amount', 'discount_thousands' => 25]);
        $this->actingAs($this->asRole('staff'));

        $this->get("$this->base/{$percent->id}/edit")
            ->assertSee('name="discount_percent" value="10"', false)
            ->assertDontSee('name="discount_thousands"', false);

        $this->get("$this->base/{$amount->id}/edit")
            ->assertSee('name="discount_thousands" value="25"', false)
            ->assertDontSee('name="discount_percent"', false);
    }

    public function test_unchanged_pickup_of_an_earlier_time_can_be_saved(): void
    {
        $order = $this->order();
        $this->travelTo(Carbon::parse('2026-12-01 18:00'));

        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3]))->assertSessionHasNoErrors();
        $this->put("$this->base/{$order->id}", $this->editForm($order, [$this->pho->id => 3], ['pickup_time' => '13:00']))->assertSessionHasErrors('pickup_at');
    }

    public function test_order_of_another_restaurant_cannot_be_edited(): void
    {
        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'menu', 'takeaway');
        $otherItem = $this->item($other, 'Bún', 50000);
        $this->actingAs(User::factory()->owner($other)->create())->post("/{$other->slug}/admin/takeaway", $this->form([$otherItem->id => 1]));
        $otherOrder = TakeawayOrder::forRestaurant($other)->sole();

        $this->actingAs($this->owner)->get("$this->base/{$otherOrder->id}/edit")->assertNotFound();
        $this->actingAs($this->owner)->put("$this->base/{$otherOrder->id}", $this->form([$this->pho->id => 1]))->assertNotFound();
    }

    public function test_day_list(): void
    {
        $this->order();

        $this->get($this->base)->assertOk()->assertSee('Anh Tuấn');
        $this->get("$this->base?date=2026-12-02")->assertDontSee('Anh Tuấn');
    }

    public function test_settings_save(): void
    {
        $this->get("$this->base/settings")->assertOk();

        $this->put("$this->base/settings", ['pickup_opens' => '11:00', 'pickup_closes' => '20:00'])
            ->assertSessionHasNoErrors();

        $this->post($this->base, $this->form([$this->pho->id => 1], ['pickup_time' => '10:30']))
            ->assertSessionHasErrors('pickup_at');

        $this->get("$this->base/settings")->assertDontSee('name="accepting_orders" value="1" checked', false);
    }

    public function test_another_restaurants_order_cannot_be_reached(): void
    {
        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'menu', 'takeaway');
        $otherItem = $this->item($other, 'Bún', 50000);

        $this->actingAs(User::factory()->owner($other)->create())->post("/{$other->slug}/admin/takeaway", $this->form([$otherItem->id => 1]));
        $orderOfOther = TakeawayOrder::forRestaurant($other)->sole();

        $this->actingAs($this->owner)->get("$this->base/{$orderOfOther->id}")->assertNotFound();
        $this->actingAs($this->owner)->post("$this->base/{$orderOfOther->id}/status", ['status' => 'cancelled'])->assertNotFound();
    }
}
