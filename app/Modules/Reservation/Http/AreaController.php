<?php

namespace App\Modules\Reservation\Http;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\ReservationStatus;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function __construct(private ModuleManager $modules) {}

    public function index(): View
    {
        return view('modules.reservation.areas', [
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->saved(fn () => Area::create([
            ...$this->validated($request),
            'sort_order' => SortOrder::next(Area::query()),
        ]));
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        abort_unless($area->is_active, 404);

        return $this->saved(fn () => $area->update($this->validated($request)));
    }

    /** Khu vực đã có đặt bàn thì ẩn đi để giữ lịch sử, chưa có thì xoá hẳn. */
    public function destroy(Area $area): RedirectResponse
    {
        $upcoming = $area->reservations()
            ->whereIn('status', ReservationStatus::active())
            ->where('ends_at', '>', now())
            ->count();

        if ($upcoming > 0) {
            return back()->with('error', __('reservation.area_has_upcoming', ['count' => $upcoming]));
        }

        return $this->saved(fn () => $area->reservations()->exists()
            ? $area->update(['is_active' => false])
            : $area->delete(), __('reservation.area_deleted'));
    }

    public function reorder(Request $request): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::reorder(Area::where('is_active', true), array_map('intval', $ids));

        return response()->noContent();
    }

    /** Lưu, rồi cảnh báo nếu tổng chỗ ngồi đổi khi đang nhận đặt online. */
    private function saved(callable $change, ?string $message = null): RedirectResponse
    {
        $before = Area::totalSeats();
        $change();
        $after = Area::totalSeats();

        $response = back()->with('status', $message ?? __('reservation.area_saved'));
        $settings = $this->modules->settings(tenant(), 'reservation');

        if ($before !== $after && $settings['online_enabled']) {
            $online = (int) ($settings['online_seats'] ?? $after);

            $response->with('warning', __($online > $after ? 'reservation.total_changed_over' : 'reservation.total_changed', [
                'from' => $before,
                'to' => $after,
                'online' => $online,
            ]));
        }

        return $response;
    }

    /** @return array{name: string, capacity: int} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
    }
}
