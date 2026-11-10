<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\DineIn\Models\Station;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\ReservationStatus;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Khu vực (Cài đặt chung): khu bàn của quán, mỗi khu có mã, số bàn và số chỗ
 * ngồi, dùng chung cho đặt bàn trước và lấy đơn tại bàn; khi bật Lấy đơn tại bàn
 * thì có thêm khu phục vụ (Bếp, Bar...).
 */
class CapacityController extends Controller
{
    public function __construct(private ModuleManager $modules) {}

    public function index(): View
    {
        return view('admin.restaurant.capacity', [
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            // Khu phục vụ thuộc module Lấy đơn tại bàn, có quyền riêng.
            'stations' => has_module('dinein') && request()->user()->can('dinein.settings')
                ? Station::withCount('items')->orderBy('sort_order')->orderBy('id')->get()
                : null,
            'unassigned' => has_module('dinein') ? MenuItem::whereNotNull('price_dine_in')->whereNull('dinein_station_id')->count() : 0,
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

        return $this->saved(fn () => $area->update($this->validated($request, $area)));
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

        // Ẩn thì trả mã khu lại để dùng cho khu khác.
        return $this->saved(fn () => $area->reservations()->exists()
            ? $area->update(['is_active' => false, 'code' => null])
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

        if ($before === $after || ! $this->modules->isEnabled(tenant(), 'reservation')) {
            return $response;
        }

        $settings = $this->modules->settings(tenant(), 'reservation');

        if ($settings['online_enabled']) {
            $online = (int) ($settings['online_seats'] ?? $after);

            $response->with('warning', __($online > $after ? 'reservation.total_changed_over' : 'reservation.total_changed', [
                'from' => $before,
                'to' => $after,
                'online' => $online,
            ]));
        }

        return $response;
    }

    /** @return array{name: string, code: string, table_count: int, capacity: int} */
    private function validated(Request $request, ?Area $area = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'regex:/^[A-Z]{1,2}$/', Rule::unique('areas')->where('restaurant_id', tenant()->getKey())->ignore($area)],
            'table_count' => ['required', 'integer', 'min:1', 'max:500'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
        ], [
            'code.regex' => __('reservation.area_code_invalid'),
            'code.unique' => __('reservation.area_code_taken'),
        ]);
    }
}
