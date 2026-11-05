<?php

namespace App\Modules\Reservation\Http;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private ModuleManager $modules) {}

    public function edit(): View
    {
        return view('modules.reservation.settings', [
            'restaurant' => tenant(),
            'settings' => $this->modules->settings(tenant(), 'reservation'),
            'areas' => Area::orderBy('name')->get(),
        ]);
    }

    public function updateBooking(Request $request): RedirectResponse
    {
        $this->modules->updateSettings(tenant(), 'reservation', $request->validate([
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'turn_minutes' => ['required', 'integer', 'min:15', 'max:600'],
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:120'],
        ]));

        return back()->with('status', __('reservation.settings_saved'));
    }

    public function updateHours(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hours' => ['required', 'array'],
            'hours.*.open' => ['boolean'],
            'hours.*.opens' => ['nullable', 'date_format:H:i'],
            'hours.*.closes' => ['nullable', 'date_format:H:i'],
        ]);

        $hours = [];

        foreach (range(1, 7) as $day) {
            $row = $data['hours'][$day] ?? [];

            if (empty($row['open'])) {
                $hours[$day] = null;

                continue;
            }

            if (empty($row['opens']) || empty($row['closes']) || $row['opens'] >= $row['closes']) {
                throw ValidationException::withMessages(["hours.$day.closes" => __('reservation.bad_hours')]);
            }

            $hours[$day] = ['opens' => $row['opens'], 'closes' => $row['closes']];
        }

        tenant()->update(['opening_hours' => $hours]);

        return back()->with('status', __('reservation.settings_saved'));
    }

    public function storeClosedDate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $dates = collect(tenant()->closed_dates)->reject(fn ($closed) => $closed['date'] === $data['date']);

        tenant()->update(['closed_dates' => $dates->push($data)->sortBy('date')->values()->all()]);

        return back()->with('status', __('reservation.settings_saved'));
    }

    public function destroyClosedDate(string $date): RedirectResponse
    {
        tenant()->update(['closed_dates' => collect(tenant()->closed_dates)
            ->reject(fn ($closed) => $closed['date'] === $date)
            ->values()
            ->all()]);

        return back()->with('status', __('reservation.settings_saved'));
    }

    public function storeArea(Request $request): RedirectResponse
    {
        Area::create($this->validatedArea($request));

        return back()->with('status', __('reservation.area_saved'));
    }

    public function updateArea(Request $request, Area $area): RedirectResponse
    {
        $area->update($this->validatedArea($request));

        return back()->with('status', __('reservation.area_saved'));
    }

    public function destroyArea(Area $area): RedirectResponse
    {
        if ($area->reservations()->exists()) {
            return back()->with('error', __('reservation.area_in_use'));
        }

        $area->delete();

        return back()->with('status', __('reservation.area_deleted'));
    }

    /** @return array<string, mixed> */
    private function validatedArea(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        return [
            ...$data,
            'online_bookable' => $request->boolean('online_bookable'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
