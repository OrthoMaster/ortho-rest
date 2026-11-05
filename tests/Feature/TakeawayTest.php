<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
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
