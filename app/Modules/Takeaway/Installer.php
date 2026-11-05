<?php

namespace App\Modules\Takeaway;

use App\Modules\ModuleInstaller;

class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return ['accepting_orders' => true, 'pickup_opens' => '10:00', 'pickup_closes' => '21:00'];
    }
}
