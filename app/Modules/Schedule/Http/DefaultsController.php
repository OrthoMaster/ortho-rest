<?php

namespace App\Modules\Schedule\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Schedule\Models\DefaultSlot;
use App\Modules\Schedule\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lịch mặc định theo thứ trong tuần: mỗi ô (thứ × ca × hàng) cần bao nhiêu người
 * và ai làm mặc định. Phân công áp lịch này vào tuần và nhắc ô thiếu người.
 */
class DefaultsController extends Controller
{
    public function index(): View
    {
        $shifts = Roster::shiftsByWeekday();
        // Một tuần mẫu để lấy ca của từng thứ.
        $days = Roster::week(CarbonImmutable::today());
        $users = Roster::users();
        $grid = [];

        foreach (DefaultSlot::all() as $default) {
            $grid[$default->weekday][$default->slotKey()][$default->laneKey()] = $default;
        }

        return view('modules.schedule.defaults', [
            'date' => $days[0],
            'days' => $days,
            'slots' => collect($days)->mapWithKeys(fn (CarbonImmutable $day) => [$day->dayOfWeekIso => Roster::slots($day, $shifts, true)])->all(),
            'grid' => $grid,
            'targets' => Roster::targets(),
            'users' => $users,
            'stats' => $this->stats($users),
        ]);
    }

    /** Lưu một ô: số người cần và danh sách người mặc định (gửi cả ô sau mỗi lần kéo, bỏ, đổi số). */
    public function update(Request $request): JsonResponse
    {
        $users = Roster::users();
        $data = $request->validate([
            'weekday' => ['required', 'integer', 'between:1,7'],
            'slot' => ['required', 'string', 'max:20'],
            'lane' => ['required', 'string', 'max:40'],
            'needed' => ['nullable', 'integer', 'min:0', 'max:50'],
            'users' => ['array'],
            'users.*' => ['integer', Rule::in($users->keys()->all())],
        ]);

        $weekday = (int) $data['weekday'];
        $slots = array_column(Roster::slots(Roster::week(CarbonImmutable::today())[$weekday - 1], Roster::shiftsByWeekday(), true), 'key');
        abort_unless(in_array($data['slot'], $slots, true) && isset(Roster::lanes(Roster::targets())[$data['lane']]), 422);

        $keys = ['weekday' => $weekday, 'shift_id' => $data['slot'] === Roster::WHOLE_DAY ? null : (int) $data['slot'], ...Roster::laneColumns($data['lane'])];
        $before = DefaultSlot::where($keys)->first();
        $people = array_values(array_unique(array_map('intval', $data['users'] ?? [])));
        $needed = $data['needed'] ?? null;

        if ($people === [] && $needed === null) {
            $before?->delete();
        } else {
            DefaultSlot::updateOrCreate($keys, ['needed' => $needed, 'user_ids' => $people ?: null]);
        }

        $changed = collect([...($before?->user_ids ?? []), ...$people])->unique();

        return response()->json(['stats' => $this->stats($users->only($changed->all()))]);
    }

    /**
     * Số ca, số giờ mỗi tuần theo lịch mặc định.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, array{short: string, label: string, over: bool}>
     */
    private function stats(Collection $users): array
    {
        $shifts = collect(Roster::shiftsByWeekday())->flatten(1)->keyBy('id');
        $whole = Roster::workdayMinutes();
        $totals = [];

        foreach (DefaultSlot::all() as $default) {
            $minutes = $default->shift_id ? (isset($shifts[$default->shift_id]) ? Roster::minutes($shifts[$default->shift_id]) : 0) : $whole;

            foreach ($default->user_ids ?? [] as $userId) {
                // Hai hàng trong cùng một ca chỉ tính một ca.
                $totals[$userId][$default->weekday.'|'.$default->shift_id] = $minutes;
            }
        }

        $stats = [];
        foreach ($users as $user) {
            $slots = $totals[$user->id] ?? [];
            $weekly = $user->contract_period !== 'month';
            $limit = $user->contract_hours !== null && $weekly ? (int) round($user->contract_hours * 60) : null;
            $stats[$user->id] = Roster::stat(count($slots), (int) array_sum($slots), $limit);
        }

        return $stats;
    }
}
