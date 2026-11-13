<?php

namespace Database\Seeders;

use App\Enums\ShiftType;
use App\Enums\SpecialDayType;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Models\Department;
use App\Models\Position;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\SpecialDay;
use App\Models\User;
use App\Modules\DineIn\LineLog;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\DineIn\Models\Incident;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuTag;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\Models\ReservationStatusLog;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use App\Modules\Schedule\LeaveBook;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\DefaultSlot;
use App\Modules\Schedule\Models\LeaveRequest;
use App\Modules\Schedule\Models\Task;
use App\Modules\Schedule\Models\Week;
use App\Modules\Takeaway\DiscountType;
use App\Modules\Takeaway\Models\DiscountCode;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\OrderStatus;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Nhà hàng Sài Gòn như đã hoạt động 3 tháng: menu có tuỳ chọn, 2 khu (Trong quán, Ngoài sân)
 * mỗi khu 10 bàn, một Bếp, mở 2 ca mỗi ngày trừ thứ Hai, 1 chủ, 1 quản lý, 2 bếp, 5 nhân viên.
 * Có đơn tại bàn, đặt bàn, đơn mang đi, thu ngân, sự cố, ưu đãi, đơn nghỉ phép và lịch làm
 * đã công bố tới hết tháng này. Ngày giờ tính lùi từ lúc chạy seed nên lúc nào chạy cũng mới.
 */
class SaigonSeeder extends Seeder
{
    private const SLUG = 'saigon';

    private Restaurant $restaurant;

    private CarbonImmutable $now;

    private CarbonImmutable $start;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Area> T, S */
    private array $areas = [];

    private Station $kitchen;

    private Task $managerTask;

    /** @var array<int, array{lunch: Shift, dinner: Shift}> giờ mở cửa theo thứ */
    private array $opening = [];

    /** @var array<int, array{lunch: Shift, dinner: Shift}> ca làm theo thứ */
    private array $work = [];

    /** @var array<string, MenuItem> */
    private array $menu = [];

    /** @var list<Customer> */
    private array $customers = [];

    /** @var array<string, list<array{0: string, 1: string}>> ngày|ca => [user key, lane] */
    private array $roster = [];

    /** @var list<CustomerOffer> ưu đãi chưa dùng */
    private array $openOffers = [];

    /** @var array<string, CarbonImmutable> bàn => bận tới lúc */
    private array $busy = [];

    private int $billNumber = 1200;

    /** @var list<DiningTable> bàn của 2 ngày mở cửa gần nhất, để ghi nhật ký */
    private array $recentTables = [];

    public function run(ModuleManager $modules): void
    {
        if (Restaurant::where('slug', self::SLUG)->exists()) {
            $this->command?->warn('Đã có nhà hàng Sài Gòn, bỏ qua. Muốn tạo lại: php artisan migrate:fresh --seed');

            return;
        }

        mt_srand(2026);
        // Không gửi thông báo đẩy khi tạo dữ liệu mẫu.
        config(['liresto.push.public_key' => null]);

        $this->now = CarbonImmutable::now();
        $this->start = $this->now->startOfDay()->subDays(91);

        $this->setUp($modules);

        ActivityLog::quietly(function () {
            $this->customers();
            $this->leaves();
            $this->schedule();
            $this->loyalOffers();
            $this->service();
            $this->upcoming();
        });

        $this->recentLogs();

        Auth::logout();
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        app(TenantContext::class)->forget();
    }

    /** Quán, tài khoản, module, khu vực, giờ mở cửa, menu: làm như chủ quán thiết lập trước ngày mở. */
    private function setUp(ModuleManager $modules): void
    {
        $this->at($this->start->subDays(7)->setTime(9, 0));

        $this->restaurant = Restaurant::create(['name' => 'Sài Gòn', 'slug' => self::SLUG, 'customer_retention_days' => 90]);
        app(TenantContext::class)->set($this->restaurant);

        $people = [
            'owner' => ['chunhahang@saigon.restaurant', 'Trần Minh Khoa', '0903111222', null],
            'manager' => ['quanly@saigon.restaurant', 'Lê Thu Hà', '0903222333', 'Phục vụ'],
            'bep1' => ['bep1@saigon.restaurant', 'Phạm Văn Hùng', '0904111001', 'Bếp'],
            'bep2' => ['bep2@saigon.restaurant', 'Đỗ Thị Mai', '0904111002', 'Bếp'],
            'nv1' => ['nhanvien1@saigon.restaurant', 'Nguyễn Thị Lan', '0905222001', 'Phục vụ'],
            'nv2' => ['nhanvien2@saigon.restaurant', 'Trần Quốc Bảo', '0905222002', 'Phục vụ'],
            'nv3' => ['nhanvien3@saigon.restaurant', 'Võ Minh Thư', '0905222003', 'Phục vụ'],
            'nv4' => ['nhanvien4@saigon.restaurant', 'Huỳnh Gia Huy', '0905222004', 'Phục vụ'],
            'nv5' => ['nhanvien5@saigon.restaurant', 'Phan Ngọc Ánh', '0905222005', 'Phục vụ'],
        ];

        foreach ($people as $key => [$email, $name, $phone]) {
            $this->users[$key] = DatabaseSeeder::account($email, $name, $this->restaurant, $key === 'owner' ? UserRole::Owner : UserRole::Staff, phone: $phone);
        }

        Auth::setUser($this->users['owner']);

        foreach (['website', 'menu', 'reservation', 'takeaway', 'dinein', 'kitchen', 'cashier', 'staff', 'schedule'] as $module) {
            $modules->enable($this->restaurant, $module);
        }

        $modules->updateSettings($this->restaurant, 'reservation', ['online_seats' => 50, 'online_seats_total' => 76, 'max_advance_days' => 30]);
        $modules->updateSettings($this->restaurant, 'takeaway', ['pickup_opens' => '10:30', 'pickup_closes' => '21:00']);
        $modules->updateSettings($this->restaurant, 'dinein', ['staff_window' => 30]);

        // Logo: tô phở và đôi đũa. Giữ màu mặc định.
        $path = "restaurants/{$this->restaurant->id}/logo.png";
        Storage::disk(config('liresto.uploads_disk'))->put($path, file_get_contents(__DIR__.'/assets/saigon-logo.png'));
        $this->restaurant->update(['theme' => ['logo' => $path]]);

        // Bộ phận có sẵn: Phục vụ, Bếp. Chức vụ: Quản lý, Nhân viên (có sẵn) và Đầu bếp.
        $departments = Department::pluck('id', 'name');
        $cook = new Position(['name' => 'Đầu bếp', 'permissions' => ['schedule.view', 'menu.availability'], 'sort_order' => 3]);
        $cook->restaurant_id = $this->restaurant->id;
        $cook->save();
        $positions = Position::pluck('id', 'preset');

        foreach ($people as $key => [, , , $department]) {
            $user = $this->users[$key];

            if ($key !== 'owner') {
                $user->forceFill([
                    'department_id' => $departments[$department],
                    'started_on' => $this->start->subDays(mt_rand(10, 200))->toDateString(),
                    'paid_leave_per_month' => 1,
                    'contract_hours' => str_starts_with($key, 'nv') ? 36 : 44,
                    'contract_period' => 'week',
                ])->save();
                $user->positions()->sync([match (true) {
                    $key === 'manager' => $positions['manager'],
                    str_starts_with($key, 'bep') => $cook->id,
                    default => $positions['staff'],
                }]);
            }
        }

        // Một Bếp (đồ uống phục vụ mang ra luôn, không qua khu nào). Hai khu, mỗi khu 10 bàn.
        Station::where('name', '!=', 'Bếp')->delete();
        $this->kitchen = Station::where('name', 'Bếp')->firstOrFail();
        $this->kitchen->update(['department_ids' => [$departments['Bếp']]]);
        $this->areas['T'] = tap(Area::firstOrFail())->update(['name' => 'Trong quán', 'code' => 'T', 'table_count' => 10, 'capacity' => 40, 'department_ids' => [$departments['Phục vụ']]]);
        $this->areas['S'] = Area::create(['name' => 'Ngoài sân', 'code' => 'S', 'table_count' => 10, 'capacity' => 36, 'sort_order' => 2, 'department_ids' => [$departments['Phục vụ']]]);
        $this->managerTask = Task::create(['name' => 'Quản lý ca', 'sort_order' => 1]);

        // Mở cửa 2 ca mỗi ngày, nghỉ thứ Hai. Ca làm vào sớm 30 phút, về sau khi dọn.
        foreach (range(2, 7) as $weekday) {
            $this->opening[$weekday] = [
                'lunch' => Shift::create(['type' => ShiftType::Opening, 'weekday' => $weekday, 'name' => 'Trưa', 'start_time' => '10:00', 'end_time' => '14:00']),
                'dinner' => Shift::create(['type' => ShiftType::Opening, 'weekday' => $weekday, 'name' => 'Tối', 'start_time' => '17:00', 'end_time' => '22:00']),
            ];
            $this->work[$weekday] = [
                'lunch' => Shift::create(['type' => ShiftType::Work, 'weekday' => $weekday, 'name' => 'Ca trưa', 'start_time' => '09:30', 'end_time' => '14:30']),
                'dinner' => Shift::create(['type' => ShiftType::Work, 'weekday' => $weekday, 'name' => 'Ca tối', 'start_time' => '16:30', 'end_time' => '22:30']),
            ];
        }

        // Nghỉ lễ Quốc khánh; một buổi trưa đóng cửa vì tiệc riêng cuối tháng này.
        SpecialDay::create(['date' => $this->openDay($this->start->addDays(20))->toDateString(), 'type' => SpecialDayType::Closed, 'periods' => [], 'reason' => 'Nghỉ lễ, cả quán đi chơi']);
        $party = $this->openDay($this->now->endOfMonth()->subDays(6));
        SpecialDay::create(['date' => $party->toDateString(), 'type' => SpecialDayType::Closed, 'periods' => [['name' => 'Trưa', 'start' => '10:00', 'end' => '14:00']], 'reason' => 'Tiệc riêng buổi trưa']);

        $this->menuItems();

        DiscountCode::create(['code' => 'SAIGON10', 'type' => DiscountType::Percent, 'value' => 10, 'is_active' => true]);
        DiscountCode::create(['code' => 'KHAITRUONG', 'type' => DiscountType::Amount, 'value' => 30000, 'is_active' => false]);

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    private function menuItems(): void
    {
        $menu = [
            'Khai vị' => [
                ['Gỏi cuốn tôm thịt', 'Bánh tráng cuốn tôm, thịt ba chỉ, bún, rau thơm.', 55000, 50000, [['Nước chấm', true, false, [['Mắm nêm', 0], ['Tương đậu phộng', 0]]]]],
                ['Chả giò rế', 'Chả giò rế giòn rụm, ăn kèm rau sống và nước mắm chua ngọt.', 65000, 60000, []],
                ['Gỏi ngó sen tôm thịt', 'Ngó sen giòn, tôm, thịt, đậu phộng rang, bánh phồng tôm.', 85000, 80000, [['Độ cay', true, false, [['Không cay', 0], ['Cay vừa', 0], ['Thật cay', 0]]]]],
            ],
            'Món chính' => [
                ['Phở bò đặc biệt', 'Nước dùng hầm xương 12 tiếng, tái, nạm, gầu, bò viên.', 85000, 80000, [
                    ['Size', true, false, [['Thường', 0], ['Lớn', 15000]]],
                    ['Thêm', false, true, [['Trứng chần', 10000], ['Thêm thịt', 25000], ['Thêm bánh phở', 10000]]],
                ]],
                ['Cơm tấm sườn bì chả', 'Sườn nướng than, bì, chả trứng, mỡ hành, nước mắm kẹo.', 79000, 75000, [
                    ['Thêm', false, true, [['Trứng ốp la', 10000], ['Thêm sườn', 30000]]],
                ]],
                ['Bún bò Huế', 'Giò heo, bắp bò, chả cua, sả và mắm ruốc Huế.', 82000, 78000, [
                    ['Độ cay', true, false, [['Không cay', 0], ['Cay vừa', 0], ['Thật cay', 0]]],
                ]],
                ['Hủ tiếu Nam Vang', 'Tôm, thịt bằm, gan, trứng cút, nước dùng ngọt thanh.', 78000, 75000, [
                    ['Kiểu', true, false, [['Nước', 0], ['Khô', 0]]],
                ]],
                ['Bò lúc lắc', 'Thăn bò Úc xào lửa lớn, hành tây, ớt chuông, salad.', 145000, 140000, [
                    ['Ăn kèm', true, false, [['Cơm trắng', 0], ['Khoai tây chiên', 15000], ['Bánh mì', 5000]]],
                    ['Độ chín', true, false, [['Tái', 0], ['Vừa', 0], ['Chín kỹ', 0]]],
                ]],
            ],
            'Tráng miệng' => [
                ['Chè ba màu', 'Đậu đỏ, đậu xanh, thạch lá dứa, nước cốt dừa.', 35000, 35000, []],
                ['Bánh flan caramel', 'Flan trứng gà ta, caramel đắng nhẹ, cà phê.', 30000, 30000, [['Thêm', false, true, [['Thêm cà phê', 0], ['Thêm đá bào', 0]]]]],
                ['Chè khúc bạch', 'Khúc bạch phô mai, nhãn, hạnh nhân lát.', 40000, 40000, []],
            ],
            'Thức uống' => [
                ['Cà phê sữa đá', 'Cà phê phin pha đậm, sữa đặc.', 35000, 32000, [['Đường', true, false, [['Ít ngọt', 0], ['Bình thường', 0], ['Ngọt', 0]]]]],
                ['Cà phê đen đá', null, 30000, 28000, [['Đường', true, false, [['Không đường', 0], ['Ít đường', 0], ['Bình thường', 0]]]]],
                ['Trà đá', null, 5000, null, []],
                ['Trà tắc', 'Trà xanh, tắc tươi, mật ong.', 25000, 25000, []],
                ['Nước dừa tươi', 'Dừa xiêm Bến Tre.', 40000, 40000, []],
                ['Sinh tố bơ', 'Bơ sáp Đắk Lắk, sữa đặc.', 45000, 45000, [['Đường', true, false, [['Ít ngọt', 0], ['Bình thường', 0]]]]],
                ['Nước ngọt', null, 20000, 20000, [['Loại', true, false, [['Coca-Cola', 0], ['Sprite', 0], ['Fanta', 0]]]]],
                ['Bia Sài Gòn Special', null, 28000, null, []],
                ['Bia Tiger', null, 30000, null, []],
            ],
        ];

        foreach (array_keys($menu) as $categoryOrder => $categoryName) {
            $category = MenuCategory::create(['name' => $categoryName, 'sort_order' => $categoryOrder + 1]);
            $kitchen = $categoryName !== 'Thức uống';

            foreach ($menu[$categoryName] as $order => [$name, $description, $dineIn, $takeaway, $groups]) {
                $item = MenuItem::create([
                    'menu_category_id' => $category->id,
                    'dinein_station_id' => $kitchen ? $this->kitchen->id : null,
                    'name' => $name,
                    'description' => $description,
                    'price_dine_in' => $dineIn,
                    'price_takeaway' => $takeaway,
                    'sort_order' => $order + 1,
                ]);

                foreach ($groups as $groupOrder => [$groupName, $required, $multiple, $options]) {
                    $group = $item->optionGroups()->create(['restaurant_id' => $this->restaurant->id, 'name' => $groupName, 'is_required' => $required, 'is_multiple' => $multiple, 'sort_order' => $groupOrder + 1]);

                    foreach ($options as $position => [$optionName, $price]) {
                        $group->options()->create(['restaurant_id' => $this->restaurant->id, 'name' => $optionName, 'price' => $price, 'sort_order' => $position + 1]);
                    }
                }

                $this->menu[$name] = $item->load('optionGroups.options', 'category');
            }
        }

        $tags = MenuTag::pluck('id', 'name');
        $this->menu['Phở bò đặc biệt']->syncTags([$tags['Best-seller']]);
        $this->menu['Cơm tấm sườn bì chả']->syncTags([$tags['Best-seller']]);
        $this->menu['Bò lúc lắc']->syncTags([$tags['Must-try']]);
        $this->menu['Gỏi ngó sen tôm thịt']->syncTags([$tags['Must-try']]);
    }

    /** Khách: 70 người, 15 người đầu là khách quen. */
    private function customers(): void
    {
        $family = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý'];
        $middle = ['Văn', 'Thị', 'Minh', 'Ngọc', 'Hoàng', 'Thanh', 'Quốc', 'Gia', 'Bảo', 'Thu', 'Đức', 'Kim'];
        $given = ['An', 'Bình', 'Châu', 'Dũng', 'Giang', 'Hải', 'Hạnh', 'Hiếu', 'Hương', 'Khang', 'Linh', 'Long', 'My', 'Nam', 'Nhi', 'Phát', 'Phúc', 'Quân', 'Quyên', 'Sơn', 'Tâm', 'Thảo', 'Trang', 'Trí', 'Tú', 'Uyên', 'Vy', 'Yến'];

        for ($i = 0; $i < 70; $i++) {
            $name = $family[$i % count($family)].' '.$middle[($i * 7) % count($middle)].' '.$given[($i * 5) % count($given)];
            $customer = Customer::create([
                'name' => $name,
                'phone' => '09'.str_pad((string) (12345678 + $i * 104729 % 87654321), 8, '0', STR_PAD_LEFT),
                'email' => $i % 3 === 0 ? 'khach'.($i + 1).'@example.com' : null,
            ]);
            $this->stamp($customer, $this->start->addDays(mt_rand(0, 30)));
            $this->customers[] = $customer;
        }
    }

    /** Đơn xin nghỉ: đã duyệt, từ chối, huỷ, và vài đơn đang chờ cuối tháng. */
    private function leaves(): void
    {
        $today = $this->now->startOfDay();
        $requests = [
            ['nv2', -60, 2, null, LeaveStatus::Approved, true, 'Về quê có việc gia đình', null],
            ['bep1', -41, 0, null, LeaveStatus::Approved, true, 'Đi khám sức khoẻ định kỳ', null],
            ['nv4', -26, 0, 'dinner', LeaveStatus::Approved, false, 'Đi học buổi tối', null],
            ['manager', -19, 0, null, LeaveStatus::Approved, true, 'Đám giỗ ông nội', null],
            ['nv3', -31, 0, null, LeaveStatus::Cancelled, null, 'Đi khám răng', null],
            ['nv5', -12, 1, null, LeaveStatus::Rejected, null, 'Đi Đà Lạt với bạn', 'Cuối tuần đông khách, em đổi sang ngày trong tuần nhé'],
            ['nv1', 5, 1, null, LeaveStatus::Pending, null, 'Đám cưới bạn thân ở Cần Thơ', null],
            ['bep2', 10, 0, 'lunch', LeaveStatus::Pending, null, 'Làm giấy tờ nhà', null],
            ['nv3', 8, 0, null, LeaveStatus::Approved, true, 'Đưa mẹ đi khám bệnh', null],
        ];

        foreach ($requests as [$who, $offset, $length, $shift, $status, $paid, $reason, $note]) {
            $from = $this->openDay($today->addDays($offset));
            $to = $from->addDays($length);
            $shiftIds = $shift ? [$this->work[$from->dayOfWeekIso][$shift]->id] : null;
            $asked = $from->subDays(mt_rand(5, 12))->setTime(mt_rand(9, 21), mt_rand(0, 59))->min($this->now->subHours(mt_rand(5, 40)));
            $decided = $status === LeaveStatus::Pending ? null : $asked->addHours(mt_rand(2, 30))->min($this->now->subHour());

            $leave = LeaveRequest::create([
                'user_id' => $this->users[$who]->id,
                'starts_on' => $from->toDateString(),
                'ends_on' => $to->toDateString(),
                'shift_ids' => $shiftIds,
                'days' => LeaveBook::days($from, $to, $shiftIds),
                'reason' => $reason,
                'status' => $status,
                'is_paid' => $paid,
                'decision_note' => $note,
                'created_by' => $this->users[$who]->id,
                'decided_by' => in_array($status, [LeaveStatus::Approved, LeaveStatus::Rejected], true) ? $this->users[$who === 'manager' ? 'owner' : 'manager']->id : null,
                'decided_at' => $decided,
            ]);
            $this->stamp($leave, $asked, $decided ?? $asked);
        }
    }

    /**
     * Lịch làm mặc định theo thứ, xếp từ đầu tuần đầu tiên tới hết tháng này; ai có đơn nghỉ đã
     * duyệt thì đổi người khác cùng bộ phận. Tuần nào cũng đã công bố.
     */
    private function schedule(): void
    {
        // [thứ][ca] => [người, hàng]; hàng: k = Bếp, T / S = khu bàn, m = Quản lý ca.
        $pattern = [
            2 => ['lunch' => ['bep1' => 'k', 'nv1' => 'T', 'nv2' => 'T', 'nv3' => 'S', 'owner' => 'm'], 'dinner' => ['bep1' => 'k', 'nv1' => 'T', 'nv4' => 'T', 'nv5' => 'S', 'owner' => 'm']],
            3 => ['lunch' => ['bep2' => 'k', 'nv2' => 'T', 'nv3' => 'T', 'nv4' => 'S', 'owner' => 'm'], 'dinner' => ['bep2' => 'k', 'nv2' => 'T', 'nv5' => 'T', 'nv1' => 'S', 'manager' => 'm']],
            4 => ['lunch' => ['bep1' => 'k', 'nv3' => 'T', 'nv4' => 'T', 'nv5' => 'S', 'owner' => 'm'], 'dinner' => ['bep2' => 'k', 'nv3' => 'T', 'nv1' => 'T', 'nv2' => 'S', 'manager' => 'm']],
            5 => ['lunch' => ['bep2' => 'k', 'nv4' => 'T', 'nv5' => 'T', 'nv1' => 'S', 'manager' => 'm'], 'dinner' => ['bep1' => 'k', 'bep2' => 'k', 'nv4' => 'T', 'nv2' => 'T', 'nv3' => 'S', 'nv5' => 'S', 'manager' => 'm']],
            6 => ['lunch' => ['bep1' => 'k', 'nv5' => 'T', 'nv1' => 'T', 'nv2' => 'S', 'manager' => 'm'], 'dinner' => ['bep1' => 'k', 'bep2' => 'k', 'nv5' => 'T', 'nv3' => 'T', 'nv4' => 'S', 'nv1' => 'S', 'manager' => 'm']],
            7 => ['lunch' => ['bep2' => 'k', 'nv1' => 'T', 'nv2' => 'T', 'nv3' => 'S', 'manager' => 'm'], 'dinner' => ['bep1' => 'k', 'bep2' => 'k', 'nv2' => 'T', 'nv4' => 'T', 'nv5' => 'S', 'nv3' => 'S', 'manager' => 'm']],
        ];

        foreach ($pattern as $weekday => $shifts) {
            foreach ($shifts as $shift => $people) {
                foreach (['k', 'T', 'S', 'm'] as $lane) {
                    $ids = array_map(fn (string $key) => $this->users[$key]->id, array_keys($people, $lane, true));
                    DefaultSlot::create([...$this->lane($lane), 'weekday' => $weekday, 'shift_id' => $this->work[$weekday][$shift]->id, 'needed' => count($ids), 'user_ids' => $ids]);
                }
            }
        }

        $leaves = LeaveRequest::where('status', LeaveStatus::Approved)->get();
        $onLeave = fn (string $key, CarbonImmutable $day, Shift $shift) => $leaves->contains(fn (LeaveRequest $leave) => $leave->user_id === $this->users[$key]->id
            && $day->between($leave->starts_on, $leave->ends_on) && ($leave->shift_ids === null || in_array($shift->id, $leave->shift_ids, true)));

        $first = $this->start->startOfWeek(CarbonImmutable::MONDAY);
        $last = $this->now->endOfMonth()->startOfDay();

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            if ($day->isMonday() && $day->addDay()->lte($last)) {
                $published = $day->subDays(4)->setTime(15, 30);
                $week = Week::create(['week_start' => $day->toDateString(), 'published_at' => $published->min($this->now->subHour()), 'published_by' => $this->users['manager']->id]);
                $this->stamp($week, $published->subDay()->min($this->now->subHours(2)), $published->min($this->now->subHour()));
            }

            foreach ($this->periods($day) as $shift) {
                $people = $pattern[$day->dayOfWeekIso][$shift];
                $work = $this->work[$day->dayOfWeekIso][$shift];

                foreach ($people as $key => $lane) {
                    if ($onLeave($key, $day, $work)) {
                        // Người cùng bộ phận chưa làm ca này thì vào thay.
                        $pool = $lane === 'k' ? ['bep1', 'bep2'] : ($lane === 'm' ? ['owner', 'manager'] : ['nv1', 'nv2', 'nv3', 'nv4', 'nv5']);
                        $key = collect($pool)->first(fn (string $other) => ! isset($people[$other]) && ! $onLeave($other, $day, $work));

                        if ($key === null) {
                            continue;
                        }
                    }

                    $assignment = Assignment::create([...$this->lane($lane), 'work_date' => $day->toDateString(), 'shift_id' => $work->id, 'user_id' => $this->users[$key]->id]);
                    $this->stamp($assignment, $day->subDays(6));
                    $this->roster[$day->toDateString().'|'.$shift][] = [$key, $lane];
                }
            }
        }
    }

    /** Ưu đãi khách quen do chủ quán tặng. */
    private function loyalOffers(): void
    {
        foreach (array_slice($this->customers, 0, 8) as $index => $customer) {
            $created = $this->start->addDays(10 + $index * 9)->setTime(20, mt_rand(0, 59));

            if ($created->gt($this->now)) {
                continue;
            }

            $offer = CustomerOffer::create([
                'customer_id' => $customer->id,
                'kind' => CustomerOffer::KIND_LOYAL,
                'type' => $index % 3 === 0 ? DiscountType::Amount : DiscountType::Percent,
                'value' => $index % 3 === 0 ? 50000 : 10,
                'reason' => 'Khách quen, cảm ơn đã ủng hộ quán',
                'expires_on' => $created->addMonths(2)->toDateString(),
                'created_by' => $this->users['owner']->id,
            ]);
            $this->stamp($offer, $created);
            $this->openOffers[] = $offer;
        }
    }

    /** Mỗi ngày mở cửa: khách đặt trước, khách vãng lai, đơn mang đi; chỉ những gì trước lúc chạy seed. */
    private function service(): void
    {
        $lastOpenDays = [];

        for ($day = $this->start; $day->lte($this->now); $day = $day->addDay()) {
            $periods = $this->periods($day);

            if ($periods !== []) {
                $lastOpenDays[] = $day->toDateString();
            }

            foreach ($periods as $period) {
                $this->bookings($day, $period);
                $this->walkIns($day, $period);
                $this->takeaway($day, $period);
            }
        }

        $recent = array_slice(array_filter($lastOpenDays, fn (string $date) => $date < $this->now->toDateString()), -2);
        $this->recentTables = DiningTable::whereNotNull('closed_at')->get()
            ->filter(fn (DiningTable $table) => in_array($table->created_at->toDateString(), $recent, true))->values()->all();
    }

    private function bookings(CarbonImmutable $day, string $period): void
    {
        $weekend = $day->dayOfWeekIso >= 5;
        $count = mt_rand($weekend ? 2 : 0, $weekend ? 5 : 3);

        for ($i = 0; $i < $count; $i++) {
            $at = $period === 'lunch'
                ? $day->setTime(mt_rand(11, 12), mt_rand(0, 1) * 30)
                : $day->setTime(mt_rand(18, 20), mt_rand(0, 1) * 30);

            if ($at->gt($this->now)) {
                continue;
            }

            $roll = mt_rand(1, 100);
            $status = match (true) {
                $roll <= 6 => ReservationStatus::NoShow,
                $roll <= 15 => ReservationStatus::Cancelled,
                $roll <= 18 => ReservationStatus::Rejected,
                default => ReservationStatus::Completed,
            };
            $reservation = $this->reservation($at, $status);

            if ($status === ReservationStatus::Completed) {
                $arrived = $at->addMinutes(mt_rand(-5, 15));
                $table = $this->table($day, $period, $arrived, $reservation->area->code, $reservation->party_size, $reservation->customer, $reservation);

                if ($table === null) {
                    $reservation->update(['status' => ReservationStatus::NoShow]);
                }
            }
        }
    }

    private function walkIns(CarbonImmutable $day, string $period): void
    {
        $progress = $day->diffInDays($this->start, true) / 91;
        $weekend = $day->dayOfWeekIso >= 5;
        $base = $period === 'lunch' ? ($weekend ? 10 : 7) : ($weekend ? 15 : 10);
        $count = (int) round($base * (0.8 + 0.3 * $progress) * mt_rand(80, 120) / 100);

        for ($i = 0; $i < $count; $i++) {
            $arrived = $period === 'lunch'
                ? $day->setTime(10, 15)->addMinutes((int) ((mt_rand(0, 180) + mt_rand(0, 180)) / 2))
                : $day->setTime(17, 15)->addMinutes((int) ((mt_rand(0, 210) + mt_rand(0, 210)) / 2));

            if ($arrived->gt($this->now)) {
                continue;
            }

            $party = [1, 2, 2, 2, 3, 3, 4, 4, 5, 6][mt_rand(0, 9)];
            $area = mt_rand(1, 100) <= ($period === 'dinner' ? 50 : 70) ? 'T' : 'S';
            // Một số khách quen cho số điện thoại để gắn vào bàn.
            $customer = mt_rand(1, 100) <= 10 ? $this->customers[mt_rand(0, 14)] : null;

            $this->table($day, $period, $arrived, $area, $party, $customer);
        }
    }

    /** Một bàn: gọi món 1–2 lượt, bếp làm, mang ra, đóng bàn, thu tiền. Null nếu hết bàn. */
    private function table(CarbonImmutable $day, string $period, CarbonImmutable $arrived, string $areaCode, int $party, ?Customer $customer = null, ?Reservation $reservation = null): ?DiningTable
    {
        $label = $this->freeTable($areaCode, $arrived) ?? $this->freeTable($areaCode === 'T' ? 'S' : 'T', $arrived);

        if ($label === null) {
            return null;
        }

        $area = $this->areas[$label[0]];
        $staff = $this->onDuty($day, $period, $area->code);
        $waiter = $staff['waiter'];
        $table = DiningTable::create([
            'area_id' => $area->id,
            'table_number' => (int) substr($label, 1),
            'label' => $label,
            'customer_id' => $customer?->id,
            'reservation_id' => $reservation?->id,
            'discount_amount' => 0,
            'opened_by' => $waiter->id,
        ]);
        $this->stamp($table, $arrived);

        if ($reservation) {
            $this->statusLog($reservation, ReservationStatus::Confirmed, ReservationStatus::Seated, $waiter, $arrived);
        }

        // Lượt 1: món chính, khai vị, nước; lượt 2 (một số bàn): tráng miệng, thêm nước.
        $rounds = [[$arrived->addMinutes(mt_rand(3, 8)), $this->firstRound($party)]];

        if (mt_rand(1, 100) <= 40) {
            $rounds[] = [$rounds[0][0]->addMinutes(mt_rand(25, 45)), $this->secondRound($party)];
        }

        $lastServed = $arrived;
        $subtotal = 0;
        $slow = false;

        foreach ($rounds as $round => [$orderedAt, $items]) {
            if ($orderedAt->gt($this->now)) {
                break;
            }

            foreach ($items as [$item, $quantity, $options, $note]) {
                $line = $this->line($table, $round + 1, $item, $quantity, $options, $note, $orderedAt, $waiter, $staff['cook'], $staff['manager'], $period, $day);
                $slow = $slow || ($line->done_at && $line->done_at->diffInMinutes($orderedAt, true) >= 22);

                if ($line->status !== LineStatus::Cancelled) {
                    $subtotal += $line->unit_price * $line->quantity;
                }

                if ($line->served_at) {
                    $lastServed = $lastServed->max($line->served_at);
                }
            }
        }

        $closed = $lastServed->addMinutes(mt_rand(15, 40))->min($day->setTime($period === 'lunch' ? 14 : 22, 30));
        $this->busy[$label] = $closed->addMinutes(5);

        // Sự cố: chậm món thì hay có, còn lại thỉnh thoảng.
        if (($slow && mt_rand(1, 100) <= 15) || mt_rand(1, 1000) <= 10) {
            $this->incident($table, $slow ? 'slow' : ['wrong', 'complaint', 'broken', 'other', 'complaint'][mt_rand(0, 4)], $arrived->addMinutes(mt_rand(20, 40))->min($closed), $customer, $staff);
        }

        if ($closed->gt($this->now)) {
            return $table;
        }

        // Ưu đãi: khách quen dùng ưu đãi của mình, thỉnh thoảng có khách đưa mã ưu đãi sự cố.
        $discount = ['type' => null, 'value' => null, 'amount' => 0, 'offer' => null];
        $offer = $this->offerFor($customer, $closed);

        if ($offer) {
            $amount = $offer->type === DiscountType::Percent ? (int) round($subtotal * $offer->value / 100, -3) : min($offer->value, $subtotal);
            $discount = ['type' => $offer->type, 'value' => $offer->value, 'amount' => $amount, 'offer' => $offer];
            $offer->update(['used_at' => $closed, 'used_table_id' => $table->id]);
        } elseif (mt_rand(1, 100) <= 3) {
            $discount = ['type' => DiscountType::Percent, 'value' => 10, 'amount' => (int) round($subtotal / 10, -3), 'offer' => null];
        }

        $paid = $closed->addMinutes(mt_rand(1, 6));
        $table->update([
            'discount_type' => $discount['type'],
            'discount_value' => $discount['value'],
            'discount_amount' => $discount['amount'],
            'customer_offer_id' => $discount['offer']?->id,
            'closed_by' => $waiter->id,
            'closed_at' => $closed,
            'bill_number' => mt_rand(1, 100) <= 92 ? 'HD'.str_pad((string) ++$this->billNumber, 6, '0', STR_PAD_LEFT) : null,
            'paid_at' => $paid->lte($this->now) ? $paid : null,
            'paid_by' => $paid->lte($this->now) ? $staff['manager']->id : null,
            'updated_at' => $paid->min($this->now),
        ]);

        if ($reservation) {
            $this->statusLog($reservation, ReservationStatus::Seated, ReservationStatus::Completed, $waiter, $closed);
            $reservation->update(['status' => ReservationStatus::Completed]);
        }

        return $table;
    }

    /** @param list<array{0: int, 1: string, 2: string, 3: string}> $options */
    private function line(DiningTable $table, int $round, MenuItem $item, int $quantity, array $options, ?string $note, CarbonImmutable $orderedAt, User $waiter, User $cook, User $manager, string $period, CarbonImmutable $day): OrderLine
    {
        $direct = $item->dinein_station_id === null;
        $line = new OrderLine([
            'dinein_table_id' => $table->id,
            'menu_item_id' => $item->id,
            'dinein_station_id' => $item->dinein_station_id,
            'round' => $round,
            'name' => $item->name,
            'unit_price' => $item->price_dine_in + array_sum(array_column($options, 'price')),
            'quantity' => $quantity,
            'options' => $options ?: null,
            'note' => $note,
            'ordered_by' => $waiter->id,
            'created_at' => $orderedAt,
        ]);

        if ($direct) {
            $line->fill(['status' => LineStatus::Served, 'served_at' => $orderedAt, 'served_by' => $waiter->id, 'updated_at' => $orderedAt]);
            $line->save();

            return $line;
        }

        // Thỉnh thoảng huỷ món (khách đổi ý, hết món).
        if (mt_rand(1, 100) <= 2) {
            // Một phần huỷ khi bếp đã làm xong (khách bỏ về, ra nhầm): thành hao hụt.
            $wasted = mt_rand(1, 100) <= 25;
            $cancelled = $orderedAt->addMinutes($wasted ? mt_rand(12, 20) : mt_rand(2, 6));
            $line->fill(['status' => LineStatus::Cancelled, 'cancelled_at' => $cancelled, 'cancelled_by' => $manager->id, 'updated_at' => $cancelled,
                ...($wasted ? ['done_at' => $cancelled->subMinutes(2), 'done_by' => $cook->id] : [])]);
            $line->save();

            return $line;
        }

        $minutes = match ($item->category->name) {
            'Khai vị' => mt_rand(5, 9),
            'Tráng miệng' => mt_rand(3, 6),
            default => mt_rand(7, 12),
        };
        $peak = ($period === 'lunch' && $orderedAt->hour === 12) || ($period === 'dinner' && in_array($orderedAt->hour, [18, 19], true));
        $minutes += ($peak ? mt_rand(0, 4) : 0) + ($day->dayOfWeekIso >= 6 && $period === 'dinner' ? mt_rand(0, 3) : 0) + (mt_rand(1, 100) <= 3 ? mt_rand(12, 22) : 0);
        $done = $orderedAt->addMinutes($minutes)->addSeconds(mt_rand(0, 59));
        $served = $done->addMinutes(mt_rand(0, 2))->addSeconds(mt_rand(20, 59));

        $line->fill(match (true) {
            $done->gt($this->now) => ['status' => LineStatus::New, 'updated_at' => $orderedAt],
            $served->gt($this->now) => ['status' => LineStatus::Done, 'done_at' => $done, 'done_by' => $cook->id, 'updated_at' => $done],
            default => ['status' => LineStatus::Served, 'done_at' => $done, 'done_by' => $cook->id, 'served_at' => $served, 'served_by' => $waiter->id, 'updated_at' => $served],
        });
        $line->save();

        return $line;
    }

    /** @return list<array{0: MenuItem, 1: int, 2: list<array>, 3: ?string}> */
    private function firstRound(int $party): array
    {
        $mains = ['Phở bò đặc biệt', 'Phở bò đặc biệt', 'Cơm tấm sườn bì chả', 'Cơm tấm sườn bì chả', 'Bún bò Huế', 'Hủ tiếu Nam Vang', 'Bò lúc lắc'];
        $starters = ['Gỏi cuốn tôm thịt', 'Chả giò rế', 'Gỏi ngó sen tôm thịt'];
        $drinks = ['Trà đá', 'Trà đá', 'Cà phê sữa đá', 'Trà tắc', 'Nước dừa tươi', 'Sinh tố bơ', 'Nước ngọt', 'Bia Sài Gòn Special', 'Bia Tiger', 'Cà phê đen đá'];
        $picked = [];

        for ($i = 0; $i < $party; $i++) {
            $picked[] = $mains[mt_rand(0, count($mains) - 1)];
            $picked[] = $drinks[mt_rand(0, count($drinks) - 1)];
        }

        for ($i = 0, $n = $party >= 2 ? mt_rand(0, (int) ceil($party / 2)) : mt_rand(0, 1) * mt_rand(0, 1); $i < $n; $i++) {
            $picked[] = $starters[mt_rand(0, 2)];
        }

        return $this->group($picked);
    }

    private function secondRound(int $party): array
    {
        $extra = ['Chè ba màu', 'Bánh flan caramel', 'Chè khúc bạch', 'Bánh flan caramel', 'Trà đá', 'Bia Sài Gòn Special', 'Cà phê sữa đá'];
        $picked = [];

        for ($i = 0, $n = mt_rand(1, max(1, $party)); $i < $n; $i++) {
            $picked[] = $extra[mt_rand(0, count($extra) - 1)];
        }

        return $this->group($picked);
    }

    /** Gom món giống nhau (cùng tuỳ chọn, ghi chú) thành một dòng có số lượng. */
    private function group(array $names): array
    {
        $notes = ['Không hành', 'Ít cay', 'Mang ra trước', 'Không rau thơm', 'Ít đá', 'Thêm chanh'];
        $lines = [];

        foreach ($names as $name) {
            $item = $this->menu[$name];
            $options = $this->options($item);
            $note = mt_rand(1, 100) <= 7 ? $notes[mt_rand(0, count($notes) - 1)] : null;
            $key = $name.'|'.implode(',', array_column($options, 'id')).'|'.$note;
            $lines[$key] ??= [$item, 0, $options, $note];
            $lines[$key][1]++;
        }

        return array_values($lines);
    }

    /** Tuỳ chọn khách chọn, cùng dạng với lúc gọi món thật (ChosenOptions). */
    private function options(MenuItem $item): array
    {
        $chosen = [];

        foreach ($item->optionGroups as $group) {
            $options = $group->options->values();
            $picked = [];

            if ($group->is_multiple) {
                $picked = $options->filter(fn () => mt_rand(1, 100) <= 15)->all();
            } elseif ($group->is_required || mt_rand(1, 100) <= 30) {
                $picked = [$options[mt_rand(1, 100) <= 60 ? 0 : mt_rand(0, $options->count() - 1)]];
            }

            foreach ($picked as $option) {
                $chosen[] = ['id' => $option->id, 'group' => $group->name, 'name' => $option->name, 'price' => $option->price];
            }
        }

        return $chosen;
    }

    private function incident(DiningTable $table, string $kind, CarbonImmutable $at, ?Customer $customer, array $staff): void
    {
        $notes = [
            'slow' => ['Khách chờ món chính hơn 25 phút', 'Bếp ra món chậm giờ cao điểm, khách hỏi 2 lần', 'Bò lúc lắc ra trễ so với cả bàn'],
            'wrong' => ['Ra nhầm phở size thường thành size lớn', 'Bún bò khách dặn không cay nhưng ra cay', 'Mang nhầm món của bàn bên cạnh'],
            'complaint' => ['Khách phàn nàn nước dùng hơi mặn', 'Khách chê cơm tấm sườn hơi khô', 'Bàn ngoài sân nhiều muỗi, khách không vui'],
            'broken' => ['Vỡ ly khi dọn bàn, không ảnh hưởng khách', 'Ghế bàn ngoài sân bị lung lay, đã đổi ghế'],
            'other' => ['Khách để quên áo khoác, đã gửi quầy', 'Khách đổi bàn vì nắng chiếu'],
        ][$kind];
        $by = mt_rand(0, 1) ? $staff['waiter'] : $staff['manager'];

        $incident = Incident::create(['dinein_table_id' => $table->id, 'kind' => $kind, 'note' => mt_rand(1, 100) <= 85 ? $notes[mt_rand(0, count($notes) - 1)] : null, 'created_by' => $by->id]);
        $this->stamp($incident, $at);

        // Sự cố ảnh hưởng khách: quản lý tặng ưu đãi cho lần sau.
        if (in_array($kind, ['slow', 'wrong', 'complaint'], true) && mt_rand(1, 100) <= 55) {
            $percent = mt_rand(0, 2) > 0;
            $offer = CustomerOffer::create([
                'customer_id' => $customer?->id,
                'kind' => CustomerOffer::KIND_INCIDENT,
                'type' => $percent ? DiscountType::Percent : DiscountType::Amount,
                'value' => $percent ? [10, 15, 20][mt_rand(0, 2)] : 50000,
                'expires_on' => $at->addMonth()->toDateString(),
                'dinein_table_id' => $table->id,
                'incident_id' => $incident->id,
                'created_by' => $staff['manager']->id,
            ]);
            $this->stamp($offer, $at->addMinutes(5));
            $this->openOffers[] = $offer;
        }
    }

    /** Ưu đãi còn hạn, chưa dùng: khách quen thì của họ; khách không tên thỉnh thoảng đưa mã sự cố. */
    private function offerFor(?Customer $customer, CarbonImmutable $at): ?CustomerOffer
    {
        foreach ($this->openOffers as $index => $offer) {
            $usable = $offer->created_at->lt($at->subDay()) && $at->toDateString() <= $offer->expires_on->toDateString();
            $mine = $customer && $offer->customer_id === $customer->id && mt_rand(1, 100) <= 25;
            $anonymous = $offer->customer_id === null && $customer === null && mt_rand(1, 1000) <= 1;

            if ($usable && ($mine || $anonymous)) {
                unset($this->openOffers[$index]);

                return $offer;
            }
        }

        return null;
    }

    private function takeaway(CarbonImmutable $day, string $period): void
    {
        $count = mt_rand(1, $day->dayOfWeekIso >= 5 ? 5 : 3);

        for ($i = 0; $i < $count; $i++) {
            $pickup = $period === 'lunch'
                ? $day->setTime(10, 30)->addMinutes(mt_rand(0, 18) * 10)
                : $day->setTime(17, 30)->addMinutes(mt_rand(0, 21) * 10);

            if ($pickup->gt($this->now)) {
                continue;
            }

            $this->takeawayOrder($day, $period, $pickup);
        }
    }

    private function takeawayOrder(CarbonImmutable $day, string $period, CarbonImmutable $pickup, bool $upcoming = false): void
    {
        $online = mt_rand(1, 100) <= 45;
        $created = $pickup->subMinutes(mt_rand(20, $online ? 600 : 180))->max($day->subDay()->setTime(20, 0));
        $staff = $this->onDuty($day, $period, 'T');
        $customer = mt_rand(1, 100) <= 35 ? $this->customers[mt_rand(0, 14)] : $this->customers[mt_rand(15, 69)];
        $names = [];

        for ($n = 0, $mains = mt_rand(1, 4); $n < $mains; $n++) {
            $names[] = ['Phở bò đặc biệt', 'Cơm tấm sườn bì chả', 'Cơm tấm sườn bì chả', 'Bún bò Huế', 'Hủ tiếu Nam Vang', 'Bò lúc lắc', 'Gỏi cuốn tôm thịt', 'Chả giò rế'][mt_rand(0, 7)];
        }

        if (mt_rand(1, 100) <= 50) {
            $names[] = ['Cà phê sữa đá', 'Trà tắc', 'Sinh tố bơ', 'Nước ngọt', 'Chè ba màu'][mt_rand(0, 4)];
        }

        $items = $this->group($names);
        $subtotal = array_sum(array_map(fn ($line) => ($line[0]->price_takeaway + array_sum(array_column($line[2], 'price'))) * $line[1], $items));
        $code = mt_rand(1, 100) <= 10 ? 'SAIGON10' : null;
        $discount = $code ? (int) round($subtotal / 10, -3) : 0;
        $cancelled = ! $upcoming && mt_rand(1, 100) <= 5;
        $done = ! $upcoming && ! $cancelled;
        $delivered = $pickup->addMinutes(mt_rand(-5, 12));
        $status = match (true) {
            $cancelled => OrderStatus::Cancelled,
            $upcoming => $pickup->diffInMinutes($this->now, true) <= 30 ? OrderStatus::Preparing : OrderStatus::New,
            default => OrderStatus::Delivered,
        };

        $order = TakeawayOrder::create([
            'customer_id' => $customer->id,
            'created_by' => $online ? null : $staff['waiter']->id,
            'number' => (int) TakeawayOrder::where('pickup_date', $pickup->toDateString())->max('number') + 1,
            'code_prefix' => 'M',
            'pickup_date' => $pickup->toDateString(),
            'status' => $status,
            'pickup_at' => $pickup,
            'subtotal' => $subtotal,
            'discount_type' => $code ? DiscountType::Percent : null,
            'discount_value' => $code ? 10 : null,
            'discount_code' => $code,
            'discount_amount' => $discount,
            'total' => $subtotal - $discount,
            'notes' => mt_rand(1, 100) <= 15 ? ['Lấy thêm muỗng đũa', 'Gọi trước khi đến 5 phút', 'Đóng gói riêng nước dùng'][mt_rand(0, 2)] : null,
            'locale' => 'vi',
            'bill_number' => $done && mt_rand(1, 100) <= 90 ? 'HD'.str_pad((string) ++$this->billNumber, 6, '0', STR_PAD_LEFT) : null,
            'paid_at' => $done ? $delivered : null,
            'paid_by' => $done ? $staff['manager']->id : null,
            'ready_at' => $done ? $pickup->subMinutes(mt_rand(2, 8)) : null,
            'delivered_at' => $done ? $delivered : null,
        ]);
        $this->stamp($order, $created, $done ? $delivered : $created);

        foreach ($items as [$item, $quantity, $options, $note]) {
            $kitchen = $item->dinein_station_id !== null && $done;
            $line = $order->items()->create([
                'restaurant_id' => $this->restaurant->id,
                'menu_item_id' => $item->id,
                'dinein_station_id' => $item->dinein_station_id,
                'name' => $item->name,
                'unit_price' => $item->price_takeaway + array_sum(array_column($options, 'price')),
                'quantity' => $quantity,
                'options' => $options ?: null,
                'note' => $note,
                'done_at' => $kitchen ? $pickup->subMinutes(mt_rand(5, 15)) : null,
                'done_by' => $kitchen ? $staff['cook']->id : null,
            ]);
            $this->stamp($line, $created);
        }
    }

    /** Sau lúc chạy seed: khách đặt bàn 2 tuần tới, đơn mang đi hôm nay và ngày mai. */
    private function upcoming(): void
    {
        for ($day = $this->now->startOfDay(); $day->lte($this->now->addDays(14)); $day = $day->addDay()) {
            foreach ($this->periods($day) as $period) {
                $count = mt_rand(0, $day->dayOfWeekIso >= 5 ? 4 : 2) + ($day->diffInDays($this->now->startOfDay()) < 3 ? 1 : 0);

                for ($i = 0; $i < $count; $i++) {
                    $at = $period === 'lunch' ? $day->setTime(mt_rand(11, 12), mt_rand(0, 1) * 30) : $day->setTime(mt_rand(18, 20), mt_rand(0, 1) * 30);

                    if ($at->gt($this->now->addHour())) {
                        $this->reservation($at, mt_rand(1, 100) <= 35 ? ReservationStatus::Pending : ReservationStatus::Confirmed);
                    }
                }

                if ($day->lte($this->now->addDay())) {
                    for ($i = 0, $n = mt_rand(1, 3); $i < $n; $i++) {
                        $pickup = $period === 'lunch' ? $day->setTime(11, 0)->addMinutes(mt_rand(0, 12) * 10) : $day->setTime(18, 0)->addMinutes(mt_rand(0, 15) * 10);

                        if ($pickup->gt($this->now->addMinutes(20))) {
                            $this->takeawayOrder($day, $period, $pickup, upcoming: true);
                        }
                    }
                }
            }
        }
    }

    /** Một lượt đặt bàn và nhật ký trạng thái. */
    private function reservation(CarbonImmutable $at, ReservationStatus $status): Reservation
    {
        $online = mt_rand(1, 100) <= 45;
        $customer = mt_rand(1, 100) <= 40 ? $this->customers[mt_rand(0, 14)] : $this->customers[mt_rand(15, 69)];
        $party = [2, 2, 3, 4, 4, 5, 6, 8][mt_rand(0, 7)];
        $area = $this->areas[mt_rand(1, 100) <= 65 ? 'T' : 'S'];
        $created = $at->subDays(mt_rand(0, $online ? 10 : 5))->setTime(mt_rand(8, 21), mt_rand(0, 59))->min($at->subHours(2))->min($this->now);
        $staff = $this->onDuty($at->startOfDay(), $at->hour < 15 ? 'lunch' : 'dinner', 'T');
        $handler = $staff['manager'];

        $reservation = Reservation::create([
            'customer_id' => $customer->id,
            'area_id' => $area->id,
            'created_by' => $online ? null : $staff['waiter']->id,
            'reserved_at' => $at,
            'ends_at' => $at->addMinutes(90),
            'party_size' => $party,
            'status' => $status,
            'source' => $online ? ReservationSource::Online : ReservationSource::Phone,
            'notes' => mt_rand(1, 100) <= 25 ? ['Có em bé, cần ghế cao', 'Sinh nhật, nhờ quán chuẩn bị nến', 'Muốn ngồi gần quạt', 'Khách ăn chay 1 người', 'Đến trễ 15 phút'][mt_rand(0, 4)] : null,
            'locale' => 'vi',
        ]);
        $this->stamp($reservation, $created);

        // Đặt online chờ quán nhận; đặt qua điện thoại thì nhận luôn.
        if ($online) {
            $this->statusLog($reservation, null, ReservationStatus::Pending, null, $created);
        }

        if ($status === ReservationStatus::Pending) {
            return $reservation;
        }

        $decided = $online ? $created->addMinutes(mt_rand(10, 180))->min($at->subHour())->min($this->now) : $created;

        if ($status === ReservationStatus::Rejected) {
            $this->statusLog($reservation, ReservationStatus::Pending, ReservationStatus::Rejected, $handler, $decided);

            return $reservation;
        }

        $this->statusLog($reservation, $online ? ReservationStatus::Pending : null, ReservationStatus::Confirmed, $online ? $handler : $staff['waiter'], $decided);

        if ($status === ReservationStatus::Cancelled) {
            $this->statusLog($reservation, ReservationStatus::Confirmed, ReservationStatus::Cancelled, $staff['waiter'], $decided->addHours(mt_rand(1, 20))->min($at->subMinutes(30)));
        } elseif ($status === ReservationStatus::NoShow) {
            $this->statusLog($reservation, ReservationStatus::Confirmed, ReservationStatus::NoShow, $staff['waiter'], $at->addMinutes(30));
        }

        // Completed: Đã đến / Xong ghi khi mở, đóng bàn.
        if ($status === ReservationStatus::Completed) {
            $reservation->update(['status' => ReservationStatus::Confirmed]);
        }

        return $reservation->setRelation('customer', $customer)->setRelation('area', $area);
    }

    private function statusLog(Reservation $reservation, ?ReservationStatus $from, ReservationStatus $to, ?User $user, CarbonImmutable $at): void
    {
        ReservationStatusLog::create(['reservation_id' => $reservation->id, 'user_id' => $user?->id, 'from_status' => $from?->value, 'to_status' => $to->value, 'created_at' => $at]);
    }

    /** Nhật ký thay đổi của 2 ngày mở cửa gần nhất: gọi món, bếp xong, đóng bàn. */
    private function recentLogs(): void
    {
        foreach ($this->recentTables as $table) {
            $table->load('lines.station', 'customer');

            foreach ($table->lines->groupBy('round') as $round => $lines) {
                $this->at($lines->first()->created_at->toImmutable());
                Auth::setUser(User::find($lines->first()->ordered_by));
                LineLog::ordered($table, (int) $round, $lines);

                $cooked = $lines->filter(fn (OrderLine $line) => $line->done_at !== null);

                if ($cooked->isNotEmpty()) {
                    $this->at($cooked->max('done_at')->toImmutable());
                    Auth::setUser(User::find($cooked->first()->done_by));
                    LineLog::done($this->kitchen->name, $cooked);
                }
            }

            $valid = $table->lines->where('status', '!=', LineStatus::Cancelled);
            $this->at($table->closed_at);
            Auth::setUser(User::find($table->closed_by));
            LineLog::closed($table, $valid->sum(fn (OrderLine $line) => $line->unit_price * $line->quantity), $table->discount_amount, $valid->sum('quantity'));
        }
    }

    /** Người đang làm ca đó: phục vụ khu bàn, bếp, quản lý ca (không có thì chủ quán). */
    private function onDuty(CarbonImmutable $day, string $period, string $area): array
    {
        $roster = collect($this->roster[$day->toDateString().'|'.$period] ?? []);
        $pick = function (array $lanes) use ($roster): ?User {
            $keys = $roster->filter(fn (array $row) => in_array($row[1], $lanes, true))->pluck(0)->all();

            return $keys === [] ? null : $this->users[$keys[mt_rand(0, count($keys) - 1)]];
        };

        return [
            'waiter' => $pick([$area]) ?? $pick(['T', 'S']) ?? $this->users['manager'],
            'cook' => $pick(['k']) ?? $this->users['bep1'],
            'manager' => $pick(['m']) ?? $this->users['owner'],
        ];
    }

    private function freeTable(string $area, CarbonImmutable $at): ?string
    {
        $free = array_values(array_filter(
            array_map(fn (int $number) => $area.$number, range(1, 10)),
            fn (string $label) => ! isset($this->busy[$label]) || $this->busy[$label]->lte($at),
        ));

        return $free === [] ? null : $free[mt_rand(0, count($free) - 1)];
    }

    /** Các buổi mở cửa của ngày: trừ thứ Hai và ngày đặc biệt đóng cửa. @return list<string> */
    private function periods(CarbonImmutable $day): array
    {
        if ($day->isMonday()) {
            return [];
        }

        $special = SpecialDay::whereDate('date', $day->toDateString())->first();

        if ($special?->type === SpecialDayType::Closed) {
            $closed = array_column($special->periods, 'name');

            return $closed === [] ? [] : array_values(array_filter(['lunch', 'dinner'], fn (string $period) => ! in_array($period === 'lunch' ? 'Trưa' : 'Tối', $closed, true)));
        }

        return ['lunch', 'dinner'];
    }

    /** @return array<string, ?int> cột hàng của lịch: Bếp, khu bàn, Quản lý ca */
    private function lane(string $lane): array
    {
        return [
            'dinein_station_id' => $lane === 'k' ? $this->kitchen->id : null,
            'area_id' => in_array($lane, ['T', 'S'], true) ? $this->areas[$lane]->id : null,
            'schedule_task_id' => $lane === 'm' ? $this->managerTask->id : null,
        ];
    }

    /** Ngày mở cửa gần nhất (thứ Hai đóng cửa thì lùi sang thứ Ba). */
    private function openDay(CarbonImmutable $day): CarbonImmutable
    {
        return $day->isMonday() ? $day->addDay() : $day;
    }

    private function at(CarbonImmutable $time): void
    {
        Carbon::setTestNow($time);
        CarbonImmutable::setTestNow($time);
    }

    /** Đặt lại giờ tạo, giờ sửa như lúc việc đó xảy ra (không muộn hơn lúc chạy seed). */
    private function stamp($model, CarbonImmutable $created, ?CarbonImmutable $updated = null): void
    {
        $created = $created->min($this->now);
        $model->timestamps = false;
        $model->forceFill(['created_at' => $created, ...($model->getUpdatedAtColumn() ? ['updated_at' => ($updated ?? $created)->min($this->now)] : [])])->save();
        $model->timestamps = true;
    }
}
