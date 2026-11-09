<?php

namespace App\Modules\DineIn;

/** Món gọi tại bàn: mới gọi → khu làm xong → đã mang ra. Huỷ được khi chưa mang ra. */
enum LineStatus: string
{
    case New = 'new';
    case Done = 'done';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('dinein.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::New => 'badge-warn',
            self::Done => 'badge-ok',
            self::Served => '',
            self::Cancelled => 'badge-bad',
        };
    }

    /** Còn tính tiền. */
    public function billable(): bool
    {
        return $this !== self::Cancelled;
    }
}
