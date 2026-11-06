<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShiftType;
use App\Enums\SpecialDayType;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\SpecialDay;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpecialDayController extends Controller
{
    private const PAST_SHOWN = 20;

    public function index(): View
    {
        return view('admin.restaurant.special-days', [
            'upcoming' => SpecialDay::whereDate('date', '>=', today())->orderBy('date')->get(),
            'past' => SpecialDay::whereDate('date', '<', today())->orderByDesc('date')->limit(self::PAST_SHOWN)->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new SpecialDay(['type' => SpecialDayType::Closed, 'periods' => [], 'date' => today()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $day = SpecialDay::create($this->validated($request));

        return $this->saved($day);
    }

    public function edit(SpecialDay $specialDay): View
    {
        return $this->form($specialDay);
    }

    public function update(Request $request, SpecialDay $specialDay): RedirectResponse
    {
        $specialDay->update($this->validated($request, $specialDay));

        return $this->saved($specialDay);
    }

    public function destroy(SpecialDay $specialDay): RedirectResponse
    {
        $specialDay->delete();

        return redirect()->route('tenant.admin.restaurant.special-days.index')->with('status', __('special_days.deleted'));
    }

    private function form(SpecialDay $day): View
    {
        $shifts = Shift::where('type', ShiftType::Opening)->orderBy('start_time')->get();

        return view('admin.restaurant.special-day-form', [
            'day' => $day,
            // Ca của từng thứ, để chọn ca đóng theo đúng thứ của ngày đã chọn.
            'weekdayShifts' => $shifts->groupBy('weekday')->map(fn (Collection $day) => $day->map(fn (Shift $shift) => [
                'key' => SpecialDay::key($shift->name, $shift->start_time, $shift->end_time),
                'label' => $shift->name.' '.$shift->timeRange(),
            ])->values()),
            'templates' => $this->templates($shifts),
        ]);
    }

    /** Các ca mở cửa khác nhau trong tuần, để chọn nhanh khi mở cửa ngày đặc biệt. */
    private function templates(Collection $shifts): Collection
    {
        return $shifts->unique(fn (Shift $shift) => SpecialDay::key($shift->name, $shift->start_time, $shift->end_time))
            ->mapWithKeys(fn (Shift $shift) => [SpecialDay::key($shift->name, $shift->start_time, $shift->end_time) => [
                'name' => $shift->name, 'start' => $shift->start_time, 'end' => $shift->end_time,
            ]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SpecialDay $day = null): array
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('special_days')->where('restaurant_id', tenant()->getKey())->ignore($day)],
            'type' => ['required', Rule::enum(SpecialDayType::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'closed_shifts' => ['array'],
            'closed_shifts.*' => ['string'],
            'open_shifts' => ['array'],
            'open_shifts.*' => ['string'],
            'custom_name' => ['nullable', 'required_if_accepted:custom_enabled', 'string', 'max:50'],
            'custom_start' => ['nullable', 'required_if_accepted:custom_enabled', 'date_format:H:i'],
            'custom_end' => ['nullable', 'required_if_accepted:custom_enabled', 'date_format:H:i', 'different:custom_start'],
        ], [
            'date.unique' => __('special_days.date_taken'),
            'custom_end.different' => __('shifts.same_time'),
        ]);

        $type = SpecialDayType::from($data['type']);
        $date = CarbonImmutable::parse($data['date']);
        $shifts = Shift::where('type', ShiftType::Opening)->orderBy('start_time')->get();

        if ($type === SpecialDayType::Closed) {
            $periods = [];

            if (! $request->boolean('whole_day')) {
                $periods = $shifts->where('weekday', $date->dayOfWeekIso)
                    ->filter(fn (Shift $shift) => in_array(SpecialDay::key($shift->name, $shift->start_time, $shift->end_time), $data['closed_shifts'] ?? [], true))
                    ->map(fn (Shift $shift) => ['name' => $shift->name, 'start' => $shift->start_time, 'end' => $shift->end_time])
                    ->values()
                    ->all();

                if ($periods === []) {
                    throw ValidationException::withMessages(['closed_shifts' => __('special_days.pick_closed_shift')]);
                }
            }
        } else {
            $periods = $this->templates($shifts)->only($data['open_shifts'] ?? [])->values()->all();

            if ($request->boolean('custom_enabled')) {
                $periods[] = ['name' => $data['custom_name'], 'start' => $data['custom_start'], 'end' => $data['custom_end']];
            }

            if ($periods === []) {
                throw ValidationException::withMessages(['open_shifts' => __('special_days.pick_open_shift')]);
            }

            usort($periods, fn (array $a, array $b) => strcmp($a['start'], $b['start']));
        }

        return ['date' => $date, 'type' => $type, 'periods' => $periods, 'reason' => $data['reason'] ?? null];
    }

    /** Đóng cửa vào ngày đã có khách đặt bàn thì nhắc báo cho khách. */
    private function saved(SpecialDay $day): RedirectResponse
    {
        $response = redirect()->route('tenant.admin.restaurant.special-days.index')->with('status', __('special_days.saved'));

        if ($day->type !== SpecialDayType::Closed || ! tenant()->hasModule('reservation')) {
            return $response;
        }

        $affected = Reservation::with('customer')
            ->whereIn('status', ReservationStatus::active())
            ->whereDate('reserved_at', $day->date)
            ->get()
            ->filter(fn (Reservation $reservation) => $day->isWholeDay() || $day->overlaps($reservation->reserved_at, $reservation->ends_at));

        return $affected->isEmpty() ? $response : $response->with('warning', __('special_days.has_bookings', [
            'count' => $affected->count(),
            'names' => $affected->take(5)->map(fn (Reservation $reservation) => $reservation->customer->name.' '.$reservation->reserved_at->format('H:i'))->join(', '),
        ]));
    }
}
