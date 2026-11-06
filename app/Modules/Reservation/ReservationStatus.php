<?php

namespace App\Modules\Reservation;

use App\Models\User;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Seated = 'seated';
    case Completed = 'completed';

    public function label(): string
    {
        return __('reservation.status.'.$this->value);
    }

    public function actionLabel(): string
    {
        return __('reservation.action.'.$this->value);
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Rejected, self::Cancelled],
            self::Confirmed => [self::Seated, self::NoShow, self::Cancelled],
            self::Seated => [self::Completed],
            default => [],
        };
    }

    /**
     * Bước tiếp theo người này được làm. Nhận, từ chối hay huỷ đặt bàn đang chờ
     * cần quyền quản lý đặt bàn; đón khách, không đến, xong thì ai cũng làm được.
     *
     * @return list<self>
     */
    public function nextFor(User $user): array
    {
        return $this === self::Pending && ! $user->can('reservation.manage') ? [] : $this->next();
    }

    public function canBecome(self $status): bool
    {
        return in_array($status, $this->next(), true);
    }

    /** Còn giữ chỗ trong khu vực. */
    public function holdsSeats(): bool
    {
        return in_array($this, self::active(), true);
    }

    /** Đã được quán nhận: hiện ở tab Hôm nay cho người đón khách. */
    public static function accepted(): array
    {
        return [self::Confirmed, self::Seated, self::Completed, self::NoShow];
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed, self::Seated];
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-warn',
            self::Confirmed, self::Seated => 'badge-ok',
            self::Rejected, self::NoShow => 'badge-bad',
            default => '',
        };
    }
}
