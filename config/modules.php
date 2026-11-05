<?php

use App\Enums\UserRole;
use App\Modules\Reservation\Installer as ReservationInstaller;
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
| menu       mục menu admin: route, và các role được thấy
*/

$managers = [UserRole::Owner->value, UserRole::Manager->value];
$everyone = array_column(UserRole::cases(), 'value');

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
        'menu' => ['route' => 'website.index', 'roles' => $managers],
    ],
    'menu' => [
        'name' => 'modules.menu',
        'depends' => [],
        'phase' => 1,
        'routes' => 'Menu/routes.php',
        'menu' => ['route' => 'menu.index', 'roles' => $managers],
    ],
    'reservation' => [
        'name' => 'modules.reservation',
        'depends' => [],
        'phase' => 1,
        'installer' => ReservationInstaller::class,
        'routes' => 'Reservation/routes.php',
        'menu' => ['route' => 'reservation.index', 'roles' => $everyone],
    ],
    'takeaway' => [
        'name' => 'modules.takeaway',
        'depends' => ['menu'],
        'phase' => 1,
        'installer' => TakeawayInstaller::class,
        'routes' => 'Takeaway/routes.php',
        'menu' => ['route' => 'takeaway.index', 'roles' => $everyone],
    ],
    'staff' => [
        'name' => 'modules.staff',
        'depends' => [],
        'phase' => 2,
        'available' => false,
    ],
    'schedule' => [
        'name' => 'modules.schedule',
        'depends' => ['staff'],
        'phase' => 2,
        'available' => false,
    ],
    'leave' => [
        'name' => 'modules.leave',
        'depends' => ['staff'],
        'phase' => 2,
        'available' => false,
    ],
    'sop' => [
        'name' => 'modules.sop',
        'depends' => ['staff'],
        'phase' => 2,
        'available' => false,
    ],
];
