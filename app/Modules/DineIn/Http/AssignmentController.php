<?php

namespace App\Modules\DineIn\Http;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\Duty;
use App\Modules\DineIn\Models\Assignment;
use App\Modules\DineIn\Models\Station;
use App\Modules\Reservation\Models\Area;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Phân công theo ngày và ca làm: ai ở bếp, bar (nhận báo món mới) và ai phục vụ
 * khu vực bàn nào (nhận báo món đã xong). Ngày chưa khai ca làm thì phân công cả ngày.
 */
class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->date($request->query('date'));
        $existing = Assignment::whereDate('work_date', $date)->get()
            ->groupBy(fn (Assignment $row) => (string) $row->shift_id)
            ->map(fn (Collection $rows) => $rows->groupBy(fn (Assignment $row) => $this->targetKey($row))->map->pluck('user_id')->map->all());

        return view('modules.dinein.assign', [
            'date' => $date,
            'shifts' => Duty::shiftsOn($date),
            'targets' => $this->targets(),
            'users' => $this->users(),
            'existing' => $existing,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $date = $this->date($request->input('date'));
        $shiftIds = Duty::shiftsOn($date)->pluck('id')->all();
        $targets = $this->targets();
        $users = $this->users()->pluck('id')->all();

        $data = $request->validate([
            'shift' => [$shiftIds === [] ? 'prohibited' : 'required', 'nullable', Rule::in($shiftIds)],
            'targets' => ['array'],
            'targets.*' => ['array'],
            'targets.*.*' => ['integer', Rule::in($users)],
        ]);
        $shiftId = isset($data['shift']) ? (int) $data['shift'] : null;
        $rows = [];

        foreach ($targets as $key => $target) {
            foreach (array_unique(array_map('intval', $data['targets'][$key] ?? [])) as $userId) {
                $rows[] = [
                    'work_date' => $date->toDateString(),
                    'shift_id' => $shiftId,
                    'dinein_station_id' => $target instanceof Station ? $target->id : null,
                    'area_id' => $target instanceof Area ? $target->id : null,
                    'user_id' => $userId,
                ];
            }
        }

        DB::transaction(function () use ($date, $shiftId, $rows): void {
            Assignment::whereDate('work_date', $date)->where('shift_id', $shiftId)->delete();

            foreach ($rows as $row) {
                Assignment::create($row);
            }
        });

        $shift = $shiftId ? Shift::find($shiftId) : null;
        ActivityLog::record(tenant()->getKey(), 'dinein', __('dinein.assign_log', [
            'date' => $date->format('d/m'),
            'shift' => $shift?->name ?? __('dinein.whole_day'),
        ]));

        return redirect()->route('tenant.admin.dinein.assign.index', ['date' => $date->toDateString()])->with('status', __('dinein.assign_saved'));
    }

    private function date(mixed $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) ($value ?: 'today'))->startOfDay();
        } catch (Throwable) {
            return CarbonImmutable::today();
        }
    }

    /** @return Collection<string, Station|Area> khu (s12) rồi khu vực bàn (a3) */
    private function targets(): Collection
    {
        $stations = Station::orderBy('sort_order')->orderBy('id')->get()->keyBy(fn (Station $station) => 's'.$station->id);
        $areas = Area::where('is_active', true)->whereNotNull('code')->orderBy('sort_order')->orderBy('id')->get()->keyBy(fn (Area $area) => 'a'.$area->id);

        return $stations->union($areas);
    }

    private function targetKey(Assignment $row): string
    {
        return $row->dinein_station_id ? 's'.$row->dinein_station_id : 'a'.$row->area_id;
    }

    /** @return Collection<int, User> người đang làm ở quán */
    private function users(): Collection
    {
        // Tắt Nội bộ thì chỉ chủ quán đăng nhập được.
        return tenant()->users()->where('is_active', true)
            ->when(! has_module('staff'), fn ($query) => $query->where('role', UserRole::Owner))
            ->orderBy('name')->get();
    }
}
