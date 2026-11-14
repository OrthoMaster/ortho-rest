<?php

namespace App\Modules\Website;

use App\Modules\ModuleInstaller;

class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        // booking_page: trang đặt bàn công khai dùng mẫu của Liresto (default) hay trang quán tự tạo (custom).
        return ['theme' => 'default', 'locales' => ['vi'], 'booking_page' => 'default'];
    }
}
