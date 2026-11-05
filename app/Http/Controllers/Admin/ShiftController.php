<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShiftType;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(ShiftType $type): View
    {
        return view('admin.restaurant.shifts', [
            'type' => $type,
            'days' => Shift::where('type', $type)->orderBy('start_time')->get()->groupBy('weekday'),
        ]);
    }

    public function create(Request $request, ShiftType $type): View
    {
        $day = $request->integer('day');

        return view('admin.restaurant.shift-form', [
            'type' => $type,
            'shift' => new Shift(['start_time' => '10:00', 'end_time' => '14:00']),
            'selectedDays' => old('weekdays', $day >= 1 && $day <= 7 ? [$day] : []),
        ]);
    }

    /** Một lần thêm được cho nhiều ngày, đỡ phải nhập lại cả tuần. */
    public function store(Request $request, ShiftType $type): RedirectResponse
    {
        $data = $this->validated($request);
        $days = array_map('intval', $request->validate([
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ])['weekdays']);

        foreach ($days as $day) {
            $this->assertNoOverlap($type, $day, $data);
        }

        DB::transaction(function () use ($days, $data, $type): void {
            foreach (array_unique($days) as $day) {
                Shift::create([...$data, 'type' => $type, 'weekday' => $day]);
            }
        });

        return redirect()->route('tenant.admin.restaurant.shifts.index', $type)->with('status', __('shifts.saved'));
    }

    public function edit(Shift $shift): View
    {
        return view('admin.restaurant.shift-form', ['type' => $shift->type, 'shift' => $shift, 'selectedDays' => []]);
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $data = $this->validated($request);

        $this->assertNoOverlap($shift->type, $shift->weekday, $data, $shift);

        $shift->update($data);

        return redirect()->route('tenant.admin.restaurant.shifts.index', $shift->type)->with('status', __('shifts.saved'));
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return redirect()->route('tenant.admin.restaurant.shifts.index', $shift->type)->with('status', __('shifts.deleted'));
    }

    /** @return array{name: string, start_time: string, end_time: string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
        ], ['end_time.different' => __('shifts.same_time')]);
    }

    /**
     * Giờ mở cửa trong một ngày không được chồng nhau. Ca làm việc thì được,
     * vì nhân viên ca sáng và ca chiều thường gối đầu.
     *
     * @param  array{name: string, start_time: string, end_time: string}  $data
     */
    private function assertNoOverlap(ShiftType $type, int $day, array $data, ?Shift $ignore = null): void
    {
        if ($type !== ShiftType::Opening) {
            return;
        }

        $clash = Shift::where('type', $type)
            ->where('weekday', $day)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get()
            ->first(fn (Shift $shift): bool => $shift->overlaps($data['start_time'], $data['end_time']));

        if ($clash) {
            throw ValidationException::withMessages(['end_time' => __('shifts.overlap', [
                'day' => __('ui.calendar.weekdays')[$day - 1],
                'name' => $clash->name,
                'range' => $clash->timeRange(),
            ])]);
        }
    }
}
