<?php

namespace App\Enums;

/**
 * Chủ quán luôn toàn quyền. Mọi người khác là nhân viên, quyền theo các chức
 * vụ được giao (bảng positions).
 */
enum UserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return __('ui.roles.'.$this->value);
    }
}
