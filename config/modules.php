<?php

use App\Modules\DineIn\Installer as DineInInstaller;
use App\Modules\Kitchen\Installer as KitchenInstaller;
use App\Modules\Menu\Installer as MenuInstaller;
use App\Modules\Reservation\Installer as ReservationInstaller;
use App\Modules\Schedule\Installer as ScheduleInstaller;
use App\Modules\Staff\Installer as StaffInstaller;
use App\Modules\Takeaway\Installer as TakeawayInstaller;
use App\Modules\Website\Installer as WebsiteInstaller;

/*
| Danh sách module. DB chỉ lưu trạng thái bật/tắt (bảng restaurant_modules).
|
| core       luôn bật, không tắt được
| available  false: chưa xây (giai đoạn 2), chưa bật được
| depends    các module phải bật trước
| installer  class chạy khi bật, phải an toàn khi chạy lại
| routes     file route admin của module
| menu       mục menu admin: route, và quyền cần có để thấy
*/

return [
    'core' => [
        'name' => 'modules.core',
        'core' => true,
        'phase' => 1,
    ],
    'website' => [
        'name' => 'modules.website',
        'depends' => [],
        'phase' => 1,
        'installer' => WebsiteInstaller::class,
        'routes' => 'Website/routes.php',
        'menu' => ['route' => 'website.index', 'permission' => 'website.manage'],
    ],
    'menu' => [
        'name' => 'modules.menu',
        'depends' => [],
        'phase' => 1,
        'installer' => MenuInstaller::class,
        'routes' => 'Menu/routes.php',
        'menu' => ['route' => 'menu.index', 'permission' => ['menu.manage', 'menu.availability']],
    ],
    'reservation' => [
        'name' => 'modules.reservation',
        'depends' => [],
        'phase' => 1,
        'installer' => ReservationInstaller::class,
        'routes' => 'Reservation/routes.php',
        'menu' => ['route' => 'reservation.index', 'permission' => 'reservation.view'],
    ],
    'takeaway' => [
        'name' => 'modules.takeaway',
        'depends' => ['menu'],
        'phase' => 1,
        'installer' => TakeawayInstaller::class,
        'routes' => 'Takeaway/routes.php',
        'menu' => ['route' => 'takeaway.index', 'permission' => 'takeaway.view'],
    ],
    'dinein' => [
        'name' => 'modules.dinein',
        'depends' => ['menu'],
        'phase' => 1,
        'installer' => DineInInstaller::class,
        'routes' => 'DineIn/routes.php',
        'menu' => ['route' => 'dinein.index', 'permission' => ['dinein.order', 'dinein.settings']],
    ],
    // Màn hình khu (bếp, bar) cho món tại bàn và đơn mang đi; món không chọn khu thì không qua màn hình.
    'kitchen' => [
        'name' => 'modules.kitchen',
        'depends' => ['menu'],
        'phase' => 1,
        'installer' => KitchenInstaller::class,
        'routes' => 'Kitchen/routes.php',
        // kitchen.screen: có quyền mở mọi khu, đang được phân công ở một khu, hoặc lo đơn mang đi.
        'menu' => ['route' => 'kitchen.index', 'permission' => ['kitchen.screen']],
    ],
    'staff' => [
        'name' => 'modules.staff',
        'depends' => [],
        'phase' => 2,
        'installer' => StaffInstaller::class,
        'routes' => 'Staff/routes.php',
        'menu' => ['route' => 'staff.index', 'permission' => ['staff.manage', 'positions.manage']],
    ],
    'schedule' => [
        'name' => 'modules.schedule',
        'depends' => ['staff'],
        'phase' => 2,
        'installer' => ScheduleInstaller::class,
        'routes' => 'Schedule/routes.php',
        // schedule.open: ai cũng xem được lịch của mình; xem của mọi người cần schedule.view.
        'menu' => ['route' => 'schedule.index', 'permission' => ['schedule.open']],
    ],
    'sop' => [
        'name' => 'modules.sop',
        'depends' => ['staff'],
        'phase' => 2,
        'available' => false,
    ],
];
