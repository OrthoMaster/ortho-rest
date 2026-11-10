<?php

namespace App\Modules\Schedule;

use App\Modules\ModuleInstaller;

/** Lịch làm việc dùng ca làm ở Cài đặt chung và khu ở Cài đặt chung → Khu vực, không cần dữ liệu mẫu. */
class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        return [];
    }
}
