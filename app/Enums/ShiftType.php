<?php

namespace App\Enums;

enum ShiftType: string
{
    /** Giờ quán đón khách, ví dụ trưa và tối. */
    case Opening = 'opening';

    /** Giờ nhân viên làm, thường dài hơn giờ mở cửa. */
    case Work = 'work';

    public function label(): string
    {
        return __('shifts.type.'.$this->value);
    }

    public function hint(): string
    {
        return __('shifts.hint.'.$this->value);
    }

    public function emptyDay(): string
    {
        return __('shifts.empty_day.'.$this->value);
    }
}
