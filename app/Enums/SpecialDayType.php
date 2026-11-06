<?php

namespace App\Enums;

enum SpecialDayType: string
{
    case Closed = 'closed';
    case Open = 'open';

    public function label(): string
    {
        return __('special_days.type.'.$this->value);
    }

    public function badge(): string
    {
        return $this === self::Closed ? 'badge-bad' : 'badge-ok';
    }
}
