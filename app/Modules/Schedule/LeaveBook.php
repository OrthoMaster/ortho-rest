<?php

namespace App\Modules\Schedule;

use App\Models\User;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Sổ phép: số ngày phép có lương tích được, đã dùng, còn lại; và số ngày của một đơn.
 *
 * - Tích phép: số ngày phép mỗi tháng × số tháng đã làm đủ (tính từ ngày bắt đầu làm).
 *   Không reset theo năm, phép dư cộng dồn.
 * - Đơn nghỉ cả ngày: mỗi ngày tính 1 ngày.
 * - Đơn nghỉ theo ca: tổng giờ các ca / số giờ một ngày công, tối đa 1 ngày.
 */
class LeaveBook
{
    /** @return array{earned: float, used: float, left: float, since: ?CarbonImmutable}|null null khi chưa khai hợp đồng */
    public static function balance(User $user): ?array
    {
        if ($user->started_on === null || $user->paid_leave_per_month === null) {
            return null;
        }

        $since = CarbonImmutable::parse($user->started_on);
        $months = $since->isFuture() ? 0 : (int) floor($since->diffInMonths(CarbonImmutable::today()));
        $earned = round($months * (float) $user->paid_leave_per_month, 2);
        $used = round((float) LeaveRequest::where('user_id', $user->id)->where('status', LeaveStatus::Approved)->where('is_paid', true)->sum('days'), 2);

        return ['earned' => $earned, 'used' => $used, 'left' => round($earned - $used, 2), 'since' => $since];
    }

    /** @param list<int>|null $shiftIds */
    public static function days(CarbonImmutable $from, CarbonImmutable $to, ?array $shiftIds): float
    {
        $shifts = Roster::shiftsByWeekday();

        if ($shiftIds !== null) {
            $minutes = collect($shifts[$from->dayOfWeekIso])->whereIn('id', $shiftIds)->sum(fn ($shift) => Roster::minutes($shift));

            return min(1.0, round($minutes / max(1, Roster::workdayMinutes()), 2));
        }

        return (float) ($from->diffInDays($to) + 1);
    }

    /** @return Collection<int, Assignment> các ô trên lịch mà đơn này đè lên */
    public static function scheduled(LeaveRequest $leave): Collection
    {
        return Assignment::where('user_id', $leave->user_id)
            ->whereBetween('work_date', [$leave->starts_on->toDateString(), $leave->ends_on->toDateString()])
            ->get()
            ->filter(fn (Assignment $row) => $leave->covers($row->work_date->toImmutable(), $row->shift_id ? (string) $row->shift_id : Roster::WHOLE_DAY))
            ->values();
    }

    public static function format(float $days): string
    {
        return rtrim(rtrim(number_format($days, 2, ',', '.'), '0'), ',');
    }
}
