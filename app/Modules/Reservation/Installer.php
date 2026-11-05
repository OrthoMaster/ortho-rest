<?php

namespace App\Modules\Reservation;

use App\Modules\ModuleInstaller;

class Installer extends ModuleInstaller
{
    protected function defaultSettings(): array
    {
        return [
            'min_notice_minutes' => 60,
            'max_advance_days' => 30,
            'turn_minutes' => 90,
            'slot_interval_minutes' => 30,
        ];
    }
}
