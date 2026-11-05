<?php

namespace App\Modules\Takeaway;

use App\Modules\ModuleInstaller;

class Installer extends ModuleInstaller
{
    protected function defaultSettings(): array
    {
        return ['accepting_orders' => true];
    }
}
