<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\ModuleManager;
use App\Modules\Takeaway\DiscountType;
use App\Modules\Takeaway\Models\DiscountCode;
use App\Modules\Takeaway\Models\TakeawayOrder;
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

    /** @return array<string, mixed> */
    private function form(array $items, array $overrides = []): array
    {
        return [
            'customer_name' => 'Anh Tuấn',
            'customer_phone' => '0987654321',
            'pickup_date' => '2026-12-01',
            'pickup_time' => '12:00',
            'items' => $items,
            ...$overrides,
        ];
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
            $this->post($this->base, $this->form([$item->id => 1]))->assertSessionHasErrors('items');
        }

        $this->assertSame(0, TakeawayOrder::forRestaurant($this->restaurant)->count());
    }

    public function test_item_of_another_restaurant_is_refused(): void
    {
        $other = Restaurant::factory()->create();
        $otherItem = $this->item($other, 'Của quán khác', 1000);

        $this->post($this->base, $this->form([$otherItem->id => 1]))->assertSessionHasErrors('items');
    }

    public function test_empty_order_is_refused(): void
    {
        $this->post($this->base, $this->form([$this->pho->id => 0]))->assertSessionHasErrors('items');
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

    public function test_order_form_lists_takeaway_items_and_marks_sold_out(): void
    {
        $this->item($this->restaurant, 'Đã hết', 10000, ['is_sold_out' => true]);
        $this->item($this->restaurant, 'Chỉ tại quán', null);
        $this->item($this->restaurant, 'Đang ẩn món', 10000, ['is_visible' => false]);

        $this->get("$this->base/create")
            ->assertOk()
            ->assertSee('name="items['.$this->pho->id.']"', false)
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
        $staff = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Staff]);

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

    private function asRole(UserRole $role): User
    {
        return User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => $role]);
    }

    public function test_direct_discount_defaults_to_owner_and_manager(): void
    {
        $this->assertTrue($this->owner->can('takeaway.direct-discount'));
        $this->assertTrue($this->asRole(UserRole::Manager)->can('takeaway.direct-discount'));
        $this->assertFalse($this->asRole(UserRole::Staff)->can('takeaway.direct-discount'));
    }

    public function test_user_without_the_permission_can_use_codes_but_not_direct_discounts(): void
    {
        $this->code('GIAM10', DiscountType::Percent, 10);
        $staff = $this->asRole(UserRole::Staff);
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

    public function test_owner_can_grant_and_remove_the_permission(): void
    {
        $settings = ['pickup_opens' => '10:00', 'pickup_closes' => '21:00'];
        $staff = $this->asRole(UserRole::Staff);
        $manager = $this->asRole(UserRole::Manager);

        $this->put("$this->base/settings", [...$settings, 'direct_discount_roles' => ['staff', 'owner', 'khac']])->assertSessionHasNoErrors();
        $modules = app(ModuleManager::class);

        $this->assertSame(['staff'], $modules->settings($this->restaurant, 'takeaway')['direct_discount_roles']);
        $this->assertTrue($staff->can('takeaway.direct-discount'));
        $this->assertFalse($manager->can('takeaway.direct-discount'));
        $this->assertTrue($this->owner->can('takeaway.direct-discount'));

        $this->put("$this->base/settings", $settings)->assertSessionHasNoErrors();

        $this->assertSame([], $modules->settings($this->restaurant, 'takeaway')['direct_discount_roles']);
    }

    public function test_manager_cannot_change_the_permission(): void
    {
        $manager = $this->asRole(UserRole::Manager);

        $this->actingAs($manager)->get("$this->base/settings")->assertDontSee('direct_discount_roles', false);

        $this->put("$this->base/settings", [
            'pickup_opens' => '10:00', 'pickup_closes' => '21:00', 'direct_discount_roles' => ['staff'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['manager'], app(ModuleManager::class)->settings($this->restaurant, 'takeaway')['direct_discount_roles']);
    }

    public function test_order_code_has_a_type_letter_and_three_digits(): void
    {
        $order = $this->order();

        $this->assertSame('M001', $order->code());

        $order->number = 1000;
        $this->assertSame('M1000', $order->code());
    }

    public function test_numbers_restart_every_day(): void
    {
        $this->assertSame(1, $this->order()->number);
        $this->assertSame(2, $this->order()->number);

        $this->travelTo(Carbon::parse('2026-12-02 09:00'));

        $this->post($this->base, $this->form([$this->pho->id => 1], ['pickup_date' => '2026-12-02']))->assertSessionHasNoErrors();

        $order = TakeawayOrder::forRestaurant($this->restaurant)->latest('id')->first();

        $this->assertSame('M001', $order->code());
        $this->assertSame('2026-12-02', $order->order_date->toDateString());
    }

    public function test_code_is_shown_on_the_list_and_the_order(): void
    {
        $order = $this->order();

        $this->get($this->base)->assertSee('Đơn M001');
        $this->get("$this->base/{$order->id}")->assertSee('Đơn M001');
    }

    public function test_changing_items_keeps_old_prices_and_uses_current_price_for_new_items(): void
    {
        $order = $this->order();
        $this->pho->newQuery()->withoutGlobalScopes()->whereKey($this->pho->id)->update(['price_takeaway' => 99000]);
        $tra = $this->item($this->restaurant, 'Trà', 10000);

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 3, $tra->id => 1]))
            ->assertSessionHasNoErrors();

        $fresh = $order->fresh();

        $this->assertSame(220000, $fresh->total);
        $this->assertSame('M001', $fresh->code());
    }

    public function test_edit_is_recorded_with_who_and_what(): void
    {
        $order = $this->order();
        $tra = $this->item($this->restaurant, 'Trà', 10000);

        $this->put("$this->base/{$order->id}", $this->form([$tra->id => 1], ['pickup_time' => '13:00', 'notes' => 'Ít đá']))
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

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 2]))->assertSessionHasNoErrors();

        $this->actingInTenant($this->restaurant);
        $this->assertSame(0, $order->edits()->count());
    }

    public function test_status_does_not_change_when_editing(): void
    {
        $order = $this->order();
        $this->post("$this->base/{$order->id}/status", ['status' => 'preparing']);

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 3]))->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_delivered_and_cancelled_orders_cannot_be_edited(): void
    {
        $order = $this->order();
        $this->post("$this->base/{$order->id}/status", ['status' => 'cancelled']);

        $this->get("$this->base/{$order->id}")->assertDontSee(__('takeaway.edit'));
        $this->get("$this->base/{$order->id}/edit")->assertRedirect("$this->base/{$order->id}");
        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 3]))->assertSessionHasErrors('items');

        $this->assertSame(140000, $order->fresh()->total);
    }

    public function test_item_now_sold_out_stays_on_the_order(): void
    {
        $order = $this->order();
        $this->pho->newQuery()->withoutGlobalScopes()->whereKey($this->pho->id)->update(['is_sold_out' => true, 'is_visible' => false]);

        $this->get("$this->base/{$order->id}/edit")->assertOk()->assertSee('name="items['.$this->pho->id.']" value="2"', false);

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 1]))->assertSessionHasNoErrors();

        $this->assertSame(70000, $order->fresh()->total);
    }

    public function test_new_item_must_be_available(): void
    {
        $order = $this->order();
        $soldOut = $this->item($this->restaurant, 'Hết', 10000, ['is_sold_out' => true]);

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 2, $soldOut->id => 1]))->assertSessionHasErrors('items');
    }

    public function test_edit_keeps_a_code_that_was_turned_off_and_recalculates_it(): void
    {
        $code = $this->code('GIAM10', DiscountType::Percent, 10);
        $order = $this->discounted(['discount_mode' => 'code', 'discount_code' => 'GIAM10']);
        $code->update(['is_active' => false, 'value' => 50]);

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 4], [
            'discount_enabled' => 1, 'discount_mode' => 'code', 'discount_code' => 'GIAM10',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(252000, $order->fresh()->total);
    }

    public function test_staff_keeps_a_direct_discount_but_cannot_change_it(): void
    {
        $order = $this->discounted(['discount_mode' => 'percent', 'discount_percent' => 10]);
        $staff = $this->asRole(UserRole::Staff);
        $keep = ['discount_enabled' => 1, 'discount_mode' => 'percent', 'discount_percent' => 10];

        $this->actingAs($staff)->get("$this->base/{$order->id}/edit")->assertSee('Đơn đang giảm 10%');

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 3], $keep))->assertSessionHasNoErrors();
        $this->assertSame(189000, $order->fresh()->total);

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 3], [...$keep, 'discount_percent' => 50]))->assertForbidden();

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 3]))->assertSessionHasNoErrors();
        $this->assertSame(210000, $order->fresh()->total);
    }

    public function test_form_of_staff_sends_only_the_field_of_the_kept_discount(): void
    {
        $percent = $this->discounted(['discount_mode' => 'percent', 'discount_percent' => 10]);
        $amount = $this->discounted(['discount_mode' => 'amount', 'discount_thousands' => 25]);
        $this->actingAs($this->asRole(UserRole::Staff));

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

        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 3]))->assertSessionHasNoErrors();
        $this->put("$this->base/{$order->id}", $this->form([$this->pho->id => 3], ['pickup_time' => '13:00']))->assertSessionHasErrors('pickup_at');
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
