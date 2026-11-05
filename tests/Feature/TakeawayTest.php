<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
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
            'pickup_at' => '2026-12-01T12:00',
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
        $this->post($this->base, $this->form([$this->pho->id => 1], ['pickup_at' => '2026-12-01T22:30']))
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

        $this->post($this->base, $this->form([$this->pho->id => 1], ['pickup_at' => '2026-12-01T10:30']))
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
