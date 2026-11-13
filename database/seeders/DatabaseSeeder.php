<?php

namespace Database\Seeders;

use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Position;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\Models\Station;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuTag;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\ReservationBooking;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Takeaway\PlaceOrder;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Dữ liệu chạy thử ở local, chạy lại được. Mật khẩu yếu nên không chạy trên production. */
class DatabaseSeeder extends Seeder
{
    public function run(ModuleManager $modules): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Seed chạy thử không dùng cho production. Dùng: php artisan liresto:super-admin');
        }

        // Dữ liệu mẫu không ghi vào nhật ký của quán.
        ActivityLog::quietly(fn () => $this->seed($modules));
    }

    private function seed(ModuleManager $modules): void
    {
        $this->user('super@liresto.test', 'Super admin', superAdmin: true);

        $demo = Restaurant::firstOrCreate(['slug' => 'quan-demo'], [
            'name' => 'Quán Demo',
            'theme' => ['primary' => '#b5e3c8', 'secondary' => '#ffd8b8'],
        ]);
        $this->user('owner@quan-demo.test', 'Chủ Quán Demo', $demo, UserRole::Owner);
        $manager = $this->user('manager@quan-demo.test', 'Quản lý Demo', $demo, UserRole::Staff, phone: '0900000001');
        $staff = $this->user('staff@quan-demo.test', 'Nhân viên Demo', $demo, UserRole::Staff, phone: '0900000002');

        foreach (['website', 'menu', 'reservation', 'takeaway', 'dinein', 'kitchen', 'cashier', 'staff', 'schedule'] as $module) {
            $modules->enable($demo, $module);
        }

        // Quản lý và nhân viên demo nhận chức vụ mặc định cùng tên.
        foreach (['manager' => $manager, 'staff' => $staff] as $preset => $user) {
            $user->positions()->syncWithoutDetaching([Position::forRestaurant($demo)->where('preset', $preset)->value('id')]);
        }

        $this->demoData($demo, $modules);

        // *.localhost tự trỏ về máy mình trên Chrome, Edge, Firefox: khỏi sửa file hosts.
        $custom = Restaurant::firstOrCreate(['slug' => 'bun-cha-ha-noi'], [
            'name' => 'Bún Chả Hà Nội',
            'theme' => ['primary' => '#8c2f1b', 'secondary' => '#f2c14e'],
        ]);
        $this->user('owner@bun-cha.test', 'Chủ Bún Chả', $custom, UserRole::Owner);
        $modules->enable($custom, 'website');

        $custom->domains()->updateOrCreate(['domain' => 'bun-cha.localhost'], ['verified_at' => now()]);
    }

    /** Chỉ tạo khi quán chưa có menu, để chạy lại seed không nhân đôi dữ liệu. */
    private function demoData(Restaurant $restaurant, ModuleManager $modules): void
    {
        if (MenuCategory::forRestaurant($restaurant)->exists()) {
            return;
        }

        app(TenantContext::class)->set($restaurant);

        foreach (range(1, 7) as $day) {
            foreach ([
                [ShiftType::Opening, 'Trưa', '10:00', '14:00'],
                [ShiftType::Opening, 'Tối', '17:00', '22:00'],
                [ShiftType::Work, 'Ca sáng', '09:00', '15:00'],
                [ShiftType::Work, 'Ca tối', '16:00', '23:00'],
            ] as [$type, $name, $start, $end]) {
                Shift::create(['type' => $type, 'weekday' => $day, 'name' => $name, 'start_time' => $start, 'end_time' => $end]);
            }
        }

        $menu = [
            'Khai vị' => [['Gỏi cuốn tôm thịt', 45000, 40000], ['Chả giò', 55000, 50000]],
            'Món chính' => [['Phở bò tái', 75000, 70000], ['Bún chả', 80000, 75000], ['Cơm tấm sườn', 85000, null]],
            'Đồ uống' => [['Trà đá', 10000, null], ['Cà phê sữa đá', 35000, 30000]],
        ];

        foreach (array_keys($menu) as $categoryOrder => $categoryName) {
            $category = MenuCategory::create(['name' => $categoryName, 'sort_order' => $categoryOrder + 1]);

            foreach ($menu[$categoryName] as $itemOrder => [$name, $dineIn, $takeaway]) {
                MenuItem::create([
                    'menu_category_id' => $category->id,
                    'name' => $name,
                    'price_dine_in' => $dineIn,
                    'price_takeaway' => $takeaway,
                    'sort_order' => $itemOrder + 1,
                ]);
            }
        }

        // Lấy đơn tại bàn: đồ uống ở quầy nước, Cơm tấm có tuỳ chọn.
        // Món ăn làm ở Bếp, đồ uống ở Quầy nước (món chưa chọn khu thì không qua màn hình khu).
        [$kitchen, $bar] = Station::orderBy('sort_order')->pluck('id')->all();
        MenuItem::whereNull('dinein_station_id')->update(['dinein_station_id' => $kitchen]);
        MenuItem::whereIn('name', ['Trà đá', 'Cà phê sữa đá'])->update(['dinein_station_id' => $bar]);
        $comTam = MenuItem::where('name', 'Cơm tấm sườn')->first();
        foreach ([
            ['Thịt', true, false, [['Sườn', 0], ['Sườn cọng', 15000], ['Thêm trứng', 8000]]],
            ['Nước chấm', false, true, [['Không ớt', 0], ['Nhiều ớt', 0]]],
        ] as $order => [$name, $required, $multiple, $options]) {
            $group = $comTam->optionGroups()->create(['restaurant_id' => $restaurant->id, 'name' => $name, 'is_required' => $required, 'is_multiple' => $multiple, 'sort_order' => $order + 1]);
            foreach ($options as $position => [$optionName, $price]) {
                $group->options()->create(['restaurant_id' => $restaurant->id, 'name' => $optionName, 'price' => $price, 'sort_order' => $position + 1]);
            }
        }

        $tags = MenuTag::pluck('id', 'name');
        MenuItem::where('name', 'Phở bò tái')->first()->syncTags([$tags['Best-seller']]);
        MenuItem::where('name', 'Bún chả')->first()->syncTags([$tags['Must-try']]);

        $inside = Area::first();
        Area::create(['name' => 'Ngoài sân', 'code' => 'B', 'table_count' => 5, 'capacity' => 20, 'sort_order' => 2]);
        Area::create(['name' => 'Lầu 1', 'code' => 'L', 'table_count' => 8, 'capacity' => 30, 'sort_order' => 3]);
        $modules->updateSettings($restaurant, 'reservation', ['online_seats' => 60, 'online_seats_total' => 90]);

        $booking = app(ReservationBooking::class);
        $today = today();

        $booking->create([
            'area_id' => $inside->id, 'reserved_at' => $today->copy()->setTime(12, 0), 'party_size' => 4,
            'customer_name' => 'Anh Minh', 'customer_phone' => '0901234567',
        ], ReservationSource::Phone);
        $booking->create([
            'area_id' => $inside->id, 'reserved_at' => $today->copy()->setTime(19, 0), 'party_size' => 6,
            'customer_name' => 'Chị Lan', 'customer_phone' => '0912345678', 'notes' => 'Có em bé, cần ghế cao',
        ], ReservationSource::Phone);

        app(PlaceOrder::class)->handle(
            [
                ['item' => MenuItem::where('name', 'Phở bò tái')->value('id'), 'quantity' => 2],
                ['item' => MenuItem::where('name', 'Cà phê sữa đá')->value('id'), 'quantity' => 1],
            ],
            ['customer_name' => 'Anh Tuấn', 'customer_phone' => '0987654321', 'pickup_at' => $today->copy()->setTime(18, 30)],
        );

        app(TenantContext::class)->forget();
    }

    private function user(
        string $email,
        string $name,
        ?Restaurant $restaurant = null,
        ?UserRole $role = null,
        bool $superAdmin = false,
        ?string $phone = null,
    ): User {
        $user = User::where('email', $email)->firstOr(fn () => new User(['email' => $email]));
        $user->forceFill([
            'name' => $name,
            'password' => 'password',
            'email_verified_at' => now(),
            'restaurant_id' => $restaurant?->getKey(),
            'role' => $role,
            'is_super_admin' => $superAdmin,
            'phone' => $phone,
        ])->save();

        return $user;
    }
}
