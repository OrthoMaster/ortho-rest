<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuTag;
use App\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private User $manager;

    private MenuItem $pho;

    private string $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $modules = app(ModuleManager::class);
        foreach (['menu', 'reservation', 'takeaway'] as $module) {
            $modules->enable($this->restaurant, $module);
        }

        $this->owner = User::factory()->owner($this->restaurant)->create();
        $this->manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'role' => UserRole::Manager, 'name' => 'Quản lý Lan']);
        $this->admin = "/{$this->restaurant->slug}/admin";

        $category = MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Món chính', 'sort_order' => 1, 'is_visible' => true]);
        $this->pho = MenuItem::create([
            'restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Phở bò',
            'price_dine_in' => 75000, 'price_takeaway' => 70000, 'is_visible' => true, 'sort_order' => 1,
        ]);
    }

    private function logs()
    {
        return ActivityLog::forRestaurant($this->restaurant)->latest('id');
    }

    private function itemForm(array $overrides = []): array
    {
        return [
            'name' => 'Phở bò', 'menu_category_id' => $this->pho->menu_category_id,
            'price_dine_in' => 75000, 'price_takeaway' => 70000, 'is_visible' => 1, ...$overrides,
        ];
    }

    public function test_enabling_modules_does_not_log_the_sample_data(): void
    {
        // Ba dòng bật module, rồi hai dòng của dữ liệu tạo trong setUp, không có dữ liệu mẫu.
        $this->assertSame(
            ['Bật module Menu', 'Bật module Đặt bàn', 'Bật module Đặt mang đi', 'Thêm danh mục Món chính', 'Thêm món Phở bò'],
            $this->logs()->reorder()->oldest('id')->pluck('summary')->all(),
        );
    }

    public function test_sold_out_toggle_is_logged_with_who(): void
    {
        $this->actingAs($this->manager)->post("$this->admin/menu/items/{$this->pho->id}/toggle", ['field' => 'is_sold_out']);

        $log = $this->logs()->first();
        $this->assertSame('Báo hết món Phở bò', $log->summary);
        $this->assertSame([], $log->changes);
        $this->assertSame($this->manager->id, $log->user_id);
        $this->assertSame('menu', $log->area);
    }

    public function test_price_change_lists_old_and_new_price(): void
    {
        $this->actingAs($this->owner)->put("$this->admin/menu/items/{$this->pho->id}", $this->itemForm(['price_takeaway' => 80000]));

        $log = $this->logs()->first();
        $this->assertSame('Sửa món Phở bò', $log->summary);
        $this->assertSame(['Giá mang đi: '.vnd(70000).' → '.vnd(80000)], $log->changes);
    }

    public function test_tag_only_change_is_logged(): void
    {
        $tag = MenuTag::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Best seller']);

        $this->actingAs($this->owner)->put("$this->admin/menu/items/{$this->pho->id}", $this->itemForm(['tags' => [$tag->id]]));

        $this->assertSame(['Nhãn: (trống) → Best seller'], $this->logs()->first()->changes);
    }

    public function test_saving_without_changes_logs_nothing(): void
    {
        $before = $this->logs()->count();

        $this->actingAs($this->owner)->put("$this->admin/menu/items/{$this->pho->id}", $this->itemForm());

        $this->assertSame($before, $this->logs()->count());
    }

    public function test_settings_changes_are_logged(): void
    {
        $this->actingAs($this->owner)->put("$this->admin/takeaway/settings", [
            'pickup_opens' => '09:00', 'pickup_closes' => '21:00', 'order_code_prefix' => 'T', 'accepting_orders' => 1,
        ]);

        $log = $this->logs()->first();
        $this->assertSame('Sửa module Đặt mang đi', $log->summary);
        $this->assertContains('Giờ nhận từ: 10:00 → 09:00', $log->changes);
        $this->assertContains('Chữ đầu mã đơn: M → T', $log->changes);
        $this->assertSame('settings', $log->area);
    }

    public function test_page_is_for_the_owner_and_shows_entries(): void
    {
        $this->actingAs($this->manager)->post("$this->admin/menu/items/{$this->pho->id}/toggle", ['field' => 'is_sold_out']);

        $this->get("$this->admin/restaurant/activity")->assertForbidden();

        $this->actingAs($this->owner)->get("$this->admin/restaurant/activity")
            ->assertOk()
            ->assertSee('Báo hết món Phở bò')
            ->assertSee('Quản lý Lan');

        $this->get("$this->admin/restaurant/activity?area=reservation")->assertDontSee('Báo hết món Phở bò');
        $this->get("$this->admin/restaurant/activity?user={$this->owner->id}")->assertDontSee('Báo hết món Phở bò');
    }

    public function test_logs_of_another_restaurant_are_not_shown(): void
    {
        $other = Restaurant::factory()->create();
        ActivityLog::record($other->id, 'menu', 'Việc của quán khác');

        $this->actingAs($this->owner)->get("$this->admin/restaurant/activity")->assertDontSee('Việc của quán khác');
    }

    public function test_impersonated_changes_name_the_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create(['name' => 'Admin An']);
        $this->actingAs($admin)->post("/super/restaurants/{$this->restaurant->id}/impersonate");

        $this->post("$this->admin/menu/items/{$this->pho->id}/toggle", ['field' => 'is_visible']);

        $log = $this->logs()->first();
        $this->assertSame('Ẩn món Phở bò', $log->summary);
        $this->assertSame($admin->id, $log->impersonator_id);
        $this->assertStringContainsString('Admin An', $log->actorName());
    }

    public function test_category_image_can_be_added_and_removed(): void
    {
        Storage::fake(config('liresto.uploads_disk'));
        $category = MenuCategory::forRestaurant($this->restaurant)->find($this->pho->menu_category_id);
        $form = ['name' => 'Món chính', 'is_visible' => 1];

        $this->actingAs($this->owner)
            ->put("$this->admin/menu/categories/{$category->id}", [...$form, 'image' => UploadedFile::fake()->image('banner.jpg', 800, 300)])
            ->assertSessionHasNoErrors();

        $path = $category->fresh()->image_path;
        $this->assertNotNull($path);
        Storage::disk(config('liresto.uploads_disk'))->assertExists($path);
        $this->assertSame(['Thêm ảnh'], $this->logs()->first()->changes);

        $this->get("$this->admin/menu")->assertSee('class="category-image"', false);

        $this->put("$this->admin/menu/categories/{$category->id}", [...$form, 'remove_image' => 1]);

        $this->assertNull($category->fresh()->image_path);
        Storage::disk(config('liresto.uploads_disk'))->assertMissing($path);
    }
}
