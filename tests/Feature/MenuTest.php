<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->restaurant = Restaurant::factory()->create();
        $this->enableModules($this->restaurant, 'menu', 'takeaway');
        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->base = "/{$this->restaurant->slug}/admin/menu";
        $this->actingAs($this->owner);
    }

    private function category(string $name = 'Món chính', ?Restaurant $restaurant = null): MenuCategory
    {
        return MenuCategory::create([
            'restaurant_id' => ($restaurant ?? $this->restaurant)->id,
            'name' => $name,
        ]);
    }

    private function item(MenuCategory $category, array $attributes = []): MenuItem
    {
        return MenuItem::create([
            'restaurant_id' => $category->restaurant_id,
            'menu_category_id' => $category->id,
            'name' => 'Phở',
            'price_dine_in' => 75000,
            ...$attributes,
        ]);
    }

    public function test_category_and_item_with_two_prices(): void
    {
        $this->post("$this->base/categories", ['name' => 'Món chính'])->assertSessionHasNoErrors();
        $category = MenuCategory::forRestaurant($this->restaurant)->sole();

        $this->post("$this->base/items", [
            'menu_category_id' => $category->id,
            'name' => 'Phở bò',
            'price_dine_in' => 75000,
            'price_takeaway' => 70000,
            'is_visible' => 1,
        ])->assertSessionHasNoErrors();

        $item = MenuItem::forRestaurant($this->restaurant)->sole();
        $this->assertSame(75000, $item->price_dine_in);
        $this->assertSame(70000, $item->price_takeaway);

        $this->get($this->base)->assertOk()->assertSee('75.000 ₫')->assertSee('70.000 ₫');
    }

    public function test_item_needs_at_least_one_price(): void
    {
        $this->post("$this->base/items", ['menu_category_id' => $this->category()->id, 'name' => 'Phở'])
            ->assertSessionHasErrors(['price_dine_in', 'price_takeaway']);
    }

    public function test_tabs_filter_by_menu(): void
    {
        $category = $this->category();
        $this->item($category, ['name' => 'Chỉ tại quán', 'price_takeaway' => null]);
        $this->item($category, ['name' => 'Chỉ mang đi', 'price_dine_in' => null, 'price_takeaway' => 30000]);

        $this->get("$this->base?menu=takeaway")->assertSee('Chỉ mang đi')->assertDontSee('Chỉ tại quán');
        $this->get("$this->base?menu=dine_in")->assertSee('Chỉ tại quán')->assertDontSee('Chỉ mang đi');
    }

    public function test_page_has_search_data_for_the_browser(): void
    {
        $this->item($this->category(), ['name' => 'Phở bò', 'description' => 'Nước dùng trong']);

        $this->get($this->base)
            ->assertSee('data-search-input', false)
            ->assertSee('data-search-item="Phở bò Nước dùng trong"', false);
    }

    public function test_new_category_can_be_created_from_the_item_form(): void
    {
        $this->post("$this->base/items", [
            'menu_category_id' => 'new',
            'new_category_name' => 'Tráng miệng',
            'name' => 'Chè',
            'price_dine_in' => 30000,
        ])->assertSessionHasNoErrors();

        $category = MenuCategory::forRestaurant($this->restaurant)->sole();

        $this->assertSame('Tráng miệng', $category->name);
        $this->assertSame($category->id, MenuItem::forRestaurant($this->restaurant)->sole()->menu_category_id);
    }

    public function test_new_category_with_an_existing_name_is_reused(): void
    {
        $category = $this->category('Tráng miệng');

        $this->post("$this->base/items", [
            'menu_category_id' => 'new', 'new_category_name' => 'Tráng miệng', 'name' => 'Chè', 'price_dine_in' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, MenuCategory::forRestaurant($this->restaurant)->count());
        $this->assertSame($category->id, MenuItem::forRestaurant($this->restaurant)->sole()->menu_category_id);
    }

    public function test_new_category_needs_a_name_and_form_works_with_no_categories(): void
    {
        $this->get("$this->base/items/create")->assertOk()->assertSee(__('menu.new_category_option'));

        $this->post("$this->base/items", [
            'menu_category_id' => 'new', 'name' => 'Chè', 'price_dine_in' => 1,
        ])->assertSessionHasErrors('new_category_name');
    }

    public function test_default_tags_are_created_once_when_the_module_is_enabled(): void
    {
        $this->enableModules($this->restaurant, 'menu');

        $this->assertSame(
            ['Best-seller', 'Must-try', 'Món chay'],
            MenuTag::forRestaurant($this->restaurant)->orderBy('id')->pluck('name')->all(),
        );
    }

    public function test_item_tags_are_saved_shown_and_removed(): void
    {
        $category = $this->category();
        $tag = MenuTag::forRestaurant($this->restaurant)->where('name', 'Must-try')->sole();

        $form = ['menu_category_id' => $category->id, 'name' => 'Phở', 'price_dine_in' => 1];

        $this->post("$this->base/items", [...$form, 'tags' => [$tag->id]])->assertSessionHasNoErrors();
        $item = MenuItem::forRestaurant($this->restaurant)->sole();

        $this->assertSame([$tag->id], $item->tags()->pluck('menu_tags.id')->all());
        $this->assertDatabaseHas('menu_item_tag', ['menu_item_id' => $item->id, 'restaurant_id' => $this->restaurant->id]);
        $this->get($this->base)->assertSee('Must-try');

        $this->put("$this->base/items/{$item->id}", $form)->assertSessionHasNoErrors();
        $this->assertSame(0, $item->tags()->count());
    }

    public function test_tag_can_be_renamed_with_image_only_when_it_has_an_image(): void
    {
        $tag = MenuTag::forRestaurant($this->restaurant)->where('name', 'Best-seller')->sole();
        $png = UploadedFile::fake()->createWithContent('t.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        ));

        $this->put("$this->base/tags/{$tag->id}", ['name' => 'Recommend', 'image_only' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($tag->fresh()->image_only);

        $this->put("$this->base/tags/{$tag->id}", ['name' => 'Recommend', 'image_only' => 1, 'image' => $png])->assertSessionHasNoErrors();
        $fresh = MenuTag::forRestaurant($this->restaurant)->find($tag->id);

        $this->assertSame('Recommend', $fresh->name);
        $this->assertTrue($fresh->image_only);
        Storage::disk('public')->assertExists($fresh->image_path);

        $this->delete("$this->base/tags/{$tag->id}");
        Storage::disk('public')->assertMissing($fresh->image_path);
    }

    public function test_tag_of_another_restaurant_cannot_be_used_or_reached(): void
    {
        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'menu');
        $otherTag = MenuTag::forRestaurant($other)->first();

        $this->post("$this->base/items", [
            'menu_category_id' => $this->category()->id, 'name' => 'X', 'price_dine_in' => 1, 'tags' => [$otherTag->id],
        ])->assertSessionHasErrors('tags.0');

        $this->get("$this->base/tags/{$otherTag->id}/edit")->assertNotFound();
    }

    public function test_row_opens_the_item_and_actions_are_in_the_kebab(): void
    {
        $this->item($this->category());

        $this->get($this->base)
            ->assertSee('row-link', false)
            ->assertSee('class="kebab"', false)
            ->assertSee(__('menu.mark_sold_out'));
    }

    public function test_sold_out_and_visibility_toggle(): void
    {
        $item = $this->item($this->category());

        $this->post("$this->base/items/{$item->id}/toggle", ['field' => 'is_sold_out']);
        $this->post("$this->base/items/{$item->id}/toggle", ['field' => 'is_visible']);

        $item = MenuItem::forRestaurant($this->restaurant)->find($item->id);
        $this->assertTrue($item->is_sold_out);
        $this->assertFalse($item->is_visible);

        $this->post("$this->base/items/{$item->id}/toggle", ['field' => 'name'])->assertSessionHasErrors('field');
    }

    public function test_moving_changes_order(): void
    {
        $category = $this->category();
        $first = $this->item($category, ['name' => 'A', 'sort_order' => 1]);
        $second = $this->item($category, ['name' => 'B', 'sort_order' => 2]);

        $this->post("$this->base/items/{$second->id}/move", ['direction' => 'up']);

        $this->actingInTenant($this->restaurant);
        $this->assertSame(['B', 'A'], $category->items()->pluck('name')->all());
    }

    public function test_image_is_stored_and_deleted_with_the_item(): void
    {
        $category = $this->category();
        $png = UploadedFile::fake()->createWithContent('a.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        ));

        $this->post("$this->base/items", [
            'menu_category_id' => $category->id, 'name' => 'Phở', 'price_dine_in' => 1000, 'image' => $png,
        ])->assertSessionHasNoErrors();

        $item = MenuItem::forRestaurant($this->restaurant)->sole();
        Storage::disk('public')->assertExists($item->image_path);

        $this->delete("$this->base/items/{$item->id}");
        Storage::disk('public')->assertMissing($item->image_path);
    }

    public function test_category_with_items_cannot_be_deleted(): void
    {
        $category = $this->category();
        $this->item($category);

        $this->delete("$this->base/categories/{$category->id}")->assertSessionHas('error');
        $this->assertSame(1, MenuCategory::forRestaurant($this->restaurant)->count());
    }

    public function test_another_restaurants_data_cannot_be_reached(): void
    {
        $other = Restaurant::factory()->create();
        $otherCategory = $this->category('Của quán khác', $other);
        $otherItem = $this->item($otherCategory);

        $this->get("$this->base/items/{$otherItem->id}/edit")->assertNotFound();
        $this->delete("$this->base/items/{$otherItem->id}")->assertNotFound();
        $this->delete("$this->base/categories/{$otherCategory->id}")->assertNotFound();

        $this->post("$this->base/items", [
            'menu_category_id' => $otherCategory->id, 'name' => 'X', 'price_dine_in' => 1,
        ])->assertSessionHasErrors('menu_category_id');
    }

    public function test_staff_cannot_edit_the_menu(): void
    {
        $staff = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Staff]);

        $this->actingAs($staff)->get($this->base)->assertForbidden();
    }
}
