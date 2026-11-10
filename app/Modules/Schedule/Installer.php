<?php

namespace App\Modules\Schedule;

use App\Modules\ModuleInstaller;

/** Lịch làm việc dùng ca làm ở Cài đặt chung và khu ở Cài đặt chung → Khu vực, không cần dữ liệu mẫu. */
class Installer extends ModuleInstaller
{
    public function defaultSettings(): array
    {
        // Số giờ một ngày công: quy ca nghỉ ra ngày phép (ca 4 giờ = 0,5 ngày).
        return ['workday_hours' => 8];
    }
}
