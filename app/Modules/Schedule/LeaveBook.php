<?php

namespace App\Modules\Schedule;

use App\Models\User;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\LeaveAdjustment;
use App\Modules\Schedule\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Sổ phép: số ngày phép có lương tích được, đã dùng, còn lại; và số ngày của một đơn.
 *
 * - Tích phép: số ngày phép mỗi tháng × số tháng đã làm đủ (tính từ ngày bắt đầu làm).
 *   Không reset theo năm, phép dư cộng dồn.
 * - Người duyệt chỉnh được phép còn lại bằng tay (LeaveAdjustment), cộng / trừ vào số tích được.
 * - Đơn nghỉ cả ngày: mỗi ngày tính 1 ngày.
 * - Đơn nghỉ theo ca: tổng giờ các ca / số giờ một ngày công, tối đa 1 ngày.
 */
class LeaveBook
{
    /** @return array{earned: float, used: float, adjusted: float, left: float, since: ?CarbonImmutable}|null null khi chưa khai hợp đồng */
    public static function balance(User $user): ?array
    {
        if ($user->started_on === null || $user->paid_leave_per_month === null) {
            return null;
        }

        $since = CarbonImmutable::parse($user->started_on);
        $months = $since->isFuture() ? 0 : (int) floor($since->diffInMonths(CarbonImmutable::today()));
        $earned = round($months * (float) $user->paid_leave_per_month, 2);
        $used = round((float) LeaveRequest::where('user_id', $user->id)->where('status', LeaveStatus::Approved)->where('is_paid', true)->sum('days'), 2);
        $adjusted = round((float) LeaveAdjustment::where('user_id', $user->id)->sum('days'), 2);

        return ['earned' => $earned, 'used' => $used, 'adjusted' => $adjusted, 'left' => round($earned - $used + $adjusted, 2), 'since' => $since];
    }

    /**
     * Lịch sử phép, mới nhất trước: cộng tự động mỗi tháng, nghỉ có lương đã duyệt, chỉnh tay.
     *
     * @return list<array{at: CarbonImmutable, kind: string, days: float, after: float, text: string, note: ?string}>
     */
    public static function history(User $user): array
    {
        $balance = self::balance($user);

        if ($balance === null) {
            return [];
        }

        $entries = [];
        $perMonth = (float) $user->paid_leave_per_month;
        $months = (int) round($balance['earned'] / max($perMonth, 0.0001));

        for ($month = 1; $perMonth > 0 && $month <= $months; $month++) {
            $at = $balance['since']->addMonthsNoOverflow($month);
            $entries[] = ['at' => $at, 'kind' => 'monthly', 'days' => $perMonth, 'text' => __('leave.history_monthly', ['month' => $at->subMonthNoOverflow()->format('m/Y')]), 'note' => null];
        }

        $leaves = LeaveRequest::with('decider')->where('user_id', $user->id)->where('status', LeaveStatus::Approved)->where('is_paid', true)->get();
        foreach ($leaves as $leave) {
            $entries[] = ['at' => $leave->decided_at ?? $leave->created_at->toImmutable(), 'kind' => 'leave', 'days' => -$leave->days, 'text' => __('leave.history_leave', ['period' => $leave->periodText()]), 'note' => null];
        }

        foreach (LeaveAdjustment::with('creator')->where('user_id', $user->id)->get() as $adjustment) {
            $entries[] = ['at' => $adjustment->created_at, 'kind' => 'adjust', 'days' => $adjustment->days,
                'text' => __('leave.history_adjust', ['name' => $adjustment->creator?->name ?? '—', 'from' => self::format($adjustment->before), 'to' => self::format($adjustment->after)]),
                'note' => $adjustment->reason];
        }

        // Số còn lại sau mỗi dòng, tính theo thứ tự thời gian.
        usort($entries, fn ($a, $b) => $a['at'] <=> $b['at']);
        $running = 0.0;
        foreach ($entries as $index => $entry) {
            $running = round($running + $entry['days'], 2);
            $entries[$index]['after'] = $running;
        }

        return array_reverse($entries);
    }

    /** Chỉnh phép còn lại thành $target ngày; trả về lần chỉnh (null nếu không đổi). */
    public static function adjust(User $user, float $target, string $reason, User $by): ?LeaveAdjustment
    {
        $before = self::balance($user)['left'] ?? 0.0;
        $delta = round($target - $before, 2);

        if ($delta == 0.0) {
            return null;
        }

        return LeaveAdjustment::create(['user_id' => $user->id, 'days' => $delta, 'before' => $before, 'after' => round($target, 2), 'reason' => $reason, 'created_by' => $by->id]);
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
