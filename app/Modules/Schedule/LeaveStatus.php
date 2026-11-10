<?php

namespace App\Modules\Schedule;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('leave.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-warn',
            self::Approved => 'badge-ok',
            self::Rejected => 'badge-bad',
            self::Cancelled => '',
        };
    }
}
