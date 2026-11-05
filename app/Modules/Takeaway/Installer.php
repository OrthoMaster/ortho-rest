<?php

namespace App\Modules\Takeaway;

use App\Enums\UserRole;
use App\Modules\ModuleInstaller;

class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return [
            'accepting_orders' => true,
            'pickup_opens' => '10:00',
            'pickup_closes' => '21:00',
            // Chủ quán luôn có quyền này, đây là các vai trò được cấp thêm.
            'direct_discount_roles' => [UserRole::Manager->value],
        ];
    }
}
