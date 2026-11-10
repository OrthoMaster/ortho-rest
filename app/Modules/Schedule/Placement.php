<?php

namespace App\Modules\Schedule;

use App\Models\Department;
use App\Models\User;
use App\Modules\Schedule\Models\Assignment;
use App\Modules\Schedule\Models\LeaveRequest;
use Carbon\CarbonImmutable;

/**
 * Kiểm tra khi xếp một người vào ô (ngày × ca × hàng).
 * Lỗi (không xếp): ô không có, đã có trong ô, đơn nghỉ đã duyệt.
 * Nhắc (vẫn xếp): đơn nghỉ đang chờ, đang ở hàng khác cùng ca, khác bộ phận của khu.
 */
class Placement
{
    /** @return array{error: ?string, warnings: list<string>} */
    public function check(User $user, CarbonImmutable $date, string $slotKey, string $laneKey, ?Assignment $moving = null): array
    {
        $slots = collect(Roster::slots($date, Roster::shiftsByWeekday()))->keyBy('key');
        $targets = Roster::targets();
        $lanes = Roster::lanes($targets);
        $fail = fn (string $message) => ['error' => $message, 'warnings' => []];

        if (! $slots->has($slotKey) || ! isset($lanes[$laneKey])) {
            return $fail(__('schedule.cell_missing'));
        }

        $slot = $slots[$slotKey];
        $shiftId = $slotKey === Roster::WHOLE_DAY ? null : (int) $slotKey;
        $sameSlot = Assignment::whereDate('work_date', $date)->where('shift_id', $shiftId)->where('user_id', $user->id)
            ->when($moving, fn ($query) => $query->whereKeyNot($moving->id))
            ->get();

        if ($sameSlot->contains(fn (Assignment $row) => Roster::laneKey($row) === $laneKey)) {
            return $fail(__('schedule.already_here', ['name' => $user->name]));
        }

        $warnings = [];
        $leaves = LeaveRequest::where('user_id', $user->id)->activeBetween($date, $date)->get()
            ->filter(fn (LeaveRequest $leave) => $leave->covers($date, $slotKey));

        if ($leaves->contains(fn (LeaveRequest $leave) => $leave->status === LeaveStatus::Approved)) {
            return $fail(__('schedule.leave_approved', ['name' => $user->name, 'slot' => mb_strtolower($slot['name']), 'date' => $date->format('d/m')]));
        }

        if ($leaves->isNotEmpty()) {
            $warnings[] = __('schedule.leave_pending', ['name' => $user->name, 'date' => $date->format('d/m')]);
        }

        foreach ($sameSlot as $row) {
            $other = $lanes[Roster::laneKey($row)] ?? null;
            if ($other) {
                $warnings[] = __('schedule.also_in', ['name' => $user->name, 'lane' => $this->laneName($other), 'slot' => $slot['name']]);
            }
        }

        $departments = $lanes[$laneKey]['target']['departments'];
        if ($departments !== [] && ! in_array($user->department_id, $departments, true)) {
            $warnings[] = __('schedule.other_department', [
                'name' => $user->name,
                'mine' => $user->department?->name ?? __('staff.no_department'),
                'area' => Department::whereKey($departments)->orderBy('sort_order')->pluck('name')->implode(', '),
            ]);
        }

        return ['error' => null, 'warnings' => $warnings];
    }

    /**
     * Đơn nghỉ (chờ duyệt, đã duyệt) trong tuần, theo người: để tô ô và hiện ở bảng nhân viên.
     *
     * @param  list<CarbonImmutable>  $days
     * @return array<int, list<LeaveRequest>>
     */
    public static function leavesInWeek(array $days): array
    {
        return LeaveRequest::activeBetween($days[0], $days[6])->orderBy('starts_on')->get()
            ->groupBy('user_id')->map->values()->map->all()->all();
    }

    /** @param array{target: array<string, mixed>, lane: array<string, mixed>} $lane */
    private function laneName(array $lane): string
    {
        return $lane['lane']['task_id'] ? $lane['target']['name'].' · '.$lane['lane']['name'] : $lane['target']['name'];
    }
}
