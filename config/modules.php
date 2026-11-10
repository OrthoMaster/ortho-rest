<?php

use App\Modules\DineIn\Installer as DineInInstaller;
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
        // dinein.screen: có quyền mở màn hình khu, hoặc đang được phân công ở một khu (bếp, bar).
        'menu' => ['route' => 'dinein.index', 'permission' => ['dinein.order', 'dinein.screen', 'dinein.settings']],
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
