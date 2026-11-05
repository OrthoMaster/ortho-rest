<?php

namespace App\Modules\Website;

use App\Modules\ModuleInstaller;

class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return ['theme' => 'default', 'locales' => ['vi']];
    }
}
