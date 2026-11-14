<?php

namespace App\Modules\Reservation\Http;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\ReservationBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private ModuleManager $modules) {}

    public function edit(): View
    {
        $settings = $this->modules->settings(tenant(), 'reservation');
        $total = Area::totalSeats();
        $savedTotal = $settings['online_seats_total'] ?? null;

        return view('modules.reservation.settings', [
            'settings' => $settings,
            'total' => $total,
            'onlineSeats' => ReservationBooking::onlineSeats($settings),
            // Tổng chỗ ngồi đã đổi kể từ lần lưu số chỗ online trước.
            'totalChangedFrom' => $savedTotal !== null && (int) $savedTotal !== $total ? (int) $savedTotal : null,
        ]);
    }

    public function updateBooking(Request $request): RedirectResponse
    {
        $total = Area::totalSeats();
        $online = $request->boolean('online_enabled');

        $data = $request->validate([
            'turn_minutes' => ['required', 'integer', 'min:15', 'max:600'],
            'min_notice_days' => [$online ? 'required' : 'nullable', 'integer', 'min:0', 'max:60'],
            'max_advance_days' => [$online ? 'required' : 'nullable', 'integer', 'min:1', 'max:365', 'gte:min_notice_days'],
            'online_seats' => [$online ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.max(1, $total)],
        ], [
            'online_seats.max' => __('reservation.online_seats_over', ['total' => $total]),
            'max_advance_days.gte' => __('reservation.max_before_min'),
        ]);

        $current = $this->modules->settings(tenant(), 'reservation');

        // Tắt đặt online thì giữ nguyên quy định cũ để bật lại không phải nhập lại.
        $this->modules->updateSettings(tenant(), 'reservation', [
            'turn_minutes' => (int) $data['turn_minutes'],
            'online_enabled' => $online,
            'min_notice_days' => $online ? (int) $data['min_notice_days'] : $current['min_notice_days'],
            'max_advance_days' => $online ? (int) $data['max_advance_days'] : $current['max_advance_days'],
            'auto_confirm' => $online ? ! $request->boolean('require_confirmation') : (bool) $current['auto_confirm'],
            'online_seats' => $online ? (int) $data['online_seats'] : $current['online_seats'],
            'online_seats_total' => $online ? $total : $current['online_seats_total'],
        ]);

        return back()->with('status', __('reservation.settings_saved'));
    }
}
