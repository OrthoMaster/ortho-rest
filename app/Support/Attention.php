<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Cashier\Cashier;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationStatus;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Models\LeaveRequest;

/**
 * Việc đang chờ người này xử lý, để hiện chấm đỏ có số ở menu trái và góc thẻ ở Tổng quan:
 * đặt bàn chờ xác nhận, phiếu chờ thu tiền, đơn xin nghỉ chờ duyệt.
 * Chỉ tính module đang bật và việc người này có quyền làm. Tính một lần mỗi request.
 */
class Attention
{
    /** @return array{reservation: int, cashier: int, leave: int} */
    public static function for(User $user): array
    {
        $key = 'attention.'.$user->id;

        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }

        $counts = [
            'reservation' => has_module('reservation') && $user->can('reservation.manage')
                ? Reservation::where('status', ReservationStatus::Pending)->where('reserved_at', '>=', today())->count()
                : 0,
            'cashier' => has_module('cashier') && (has_module('dinein') || has_module('takeaway')) && $user->can('cashier.collect')
                ? Cashier::pendingCount()
                : 0,
            'leave' => has_module('schedule') && $user->can('schedule.leave')
                ? LeaveRequest::where('status', LeaveStatus::Pending)->count()
                : 0,
        ];

        request()->attributes->set($key, $counts);

        return $counts;
    }

    /** Tổng quan: các màn hình phục vụ vào từ Tổng quan (đặt bàn, thu ngân). */
    public static function dashboard(User $user): int
    {
        $counts = self::for($user);

        return $counts['reservation'] + $counts['cashier'];
    }

    public static function total(User $user): int
    {
        return array_sum(self::for($user));
    }
}
