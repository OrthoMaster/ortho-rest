<?php

namespace App\Modules\Takeaway;

enum OrderStatus: string
{
    case New = 'new';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('takeaway.status.'.$this->value);
    }

    public function actionLabel(): string
    {
        return __('takeaway.action.'.$this->value);
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::Delivered, self::Cancelled],
            default => [],
        };
    }

    public function canBecome(self $status): bool
    {
        return in_array($status, $this->next(), true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::New => 'badge-warn',
            self::Preparing, self::Ready => 'badge-ok',
            self::Cancelled => 'badge-bad',
            default => '',
        };
    }
}
