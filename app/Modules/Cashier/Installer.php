<?php

namespace App\Modules\Cashier;

use App\Modules\ModuleInstaller;

/** Thu ngân không cần dữ liệu sẵn. */
class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return [];
    }
}
