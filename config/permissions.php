<?php

/*
| Danh sách quyền, chia theo module. Quyền của module đang tắt không hiện và
| không có tác dụng. Chủ quán luôn có mọi quyền; người khác có quyền theo các
| chức vụ được giao (cần bật module Nội bộ).
|
| presets: chức vụ mặc định được giao sẵn quyền này khi bật module.
*/

return [
    'core' => [
        'customers.view' => ['manager', 'staff'],
        'customers.export' => [],
        'customers.anonymize' => [],
        'activity.view' => [],
        'settings.manage' => [],
    ],
    'dinein' => [
        'dinein.order' => ['manager', 'staff'],
        'dinein.close' => ['manager', 'staff'],
        'dinein.cancel' => ['manager'],
        'dinein.stations' => ['manager'],
        'dinein.settings' => ['manager'],
    ],
    'schedule' => [
        'schedule.view' => ['manager', 'staff'],
        'schedule.manage' => ['manager'],
    ],
    'staff' => [
        'staff.manage' => [],
        'positions.manage' => [],
    ],
    'website' => [
        'website.manage' => ['manager'],
    ],
    'menu' => [
        'menu.manage' => ['manager'],
        'menu.availability' => ['manager'],
    ],
    'reservation' => [
        'reservation.view' => ['manager', 'staff'],
        'reservation.create' => ['manager', 'staff'],
        'reservation.status' => ['manager', 'staff'],
        'reservation.manage' => ['manager'],
        'reservation.settings' => ['manager'],
    ],
    'takeaway' => [
        'takeaway.view' => ['manager', 'staff'],
        'takeaway.create' => ['manager', 'staff'],
        'takeaway.status' => ['manager', 'staff'],
        'takeaway.direct-discount' => ['manager'],
        'takeaway.settings' => ['manager'],
    ],
];
