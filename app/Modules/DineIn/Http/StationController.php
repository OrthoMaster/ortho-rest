<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Takeaway\Models\TakeawayOrderItem;
use App\Modules\Takeaway\OrderStatus;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Khu phục vụ (Bếp, Bar...) và món của từng khu. */
class StationController extends Controller
{
    /** Khu phục vụ nằm ở Cài đặt chung → Khu vực. */
    public function index(): RedirectResponse
    {
        return redirect()->route('tenant.admin.restaurant.capacity.index', status: 301);
    }

    public function store(Request $request): RedirectResponse
    {
        $station = Station::create([...$this->validated($request, null, 'station-dialog'), 'sort_order' => SortOrder::next(Station::query())]);

        return redirect()->route('tenant.admin.dinein.stations.edit', $station)->with('status', __('dinein.station_created'));
    }

    public function edit(Station $station): View
    {
        return view('modules.dinein.station-form', [
            'station' => $station,
            'kitchen' => has_module('kitchen'),
            'categories' => MenuCategory::with(['items' => fn ($query) => $query->where(fn ($query) => $query->whereNotNull('price_dine_in')->orWhereNotNull('price_takeaway'))->with('dineinStation')])
                ->orderBy('sort_order')->orderBy('id')->get()
                ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty()),
        ]);
    }

    public function update(Request $request, Station $station): RedirectResponse
    {
        $data = $this->validated($request, $station);
        $items = array_map('intval', $request->validate([
            'items' => ['array'],
            'items.*' => ['integer', Rule::exists('menu_items', 'id')->where('restaurant_id', tenant()->getKey())],
        ])['items'] ?? []);

        DB::transaction(function () use ($station, $data, $items): void {
            $station->update($data);

            // Chọn món cho khu chỉ có khi bật Màn hình khu; tắt thì giữ nguyên món của khu.
            if (! has_module('kitchen')) {
                return;
            }

            $before = $station->items()->orderBy('id')->pluck('name', 'id');
            // Bỏ chọn thì món về "chưa chọn khu" (không qua màn hình khu); chọn thì chuyển về khu này.
            MenuItem::where('dinein_station_id', $station->id)->whereKeyNot($items)->update(['dinein_station_id' => null]);
            MenuItem::whereKey($items)->update(['dinein_station_id' => $station->id]);
            $after = $station->items()->orderBy('id')->pluck('name', 'id');

            $added = $after->diffKeys($before)->values()->all();
            $removed = $before->diffKeys($after)->values()->all();

            if ($added !== [] || $removed !== []) {
                ActivityLog::record(tenant()->getKey(), 'dinein', __('dinein.station_items_log', ['name' => $station->name]), array_filter([
                    $added ? __('dinein.log_added', ['list' => implode(', ', $added)]) : null,
                    $removed ? __('dinein.log_removed', ['list' => implode(', ', $removed)]) : null,
                ]), $station);
            }
        });

        return redirect()->route('tenant.admin.restaurant.capacity.index')->with('status', __('dinein.station_saved'));
    }

    public function reorder(Request $request): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::reorder(Station::query(), array_map('intval', $ids));

        return response()->noContent();
    }

    /** Món của khu bị xoá về "chưa chọn khu". Khu còn món đang chờ làm thì chưa xoá được. */
    public function destroy(Station $station): RedirectResponse
    {
        if (Station::count() === 1) {
            return back()->with('error', __('dinein.station_last'));
        }

        $pending = OrderLine::where('dinein_station_id', $station->id)->where('status', LineStatus::New)->exists()
            || TakeawayOrderItem::where('dinein_station_id', $station->id)->whereNull('done_at')
                ->whereHas('order', fn ($query) => $query->whereIn('status', [OrderStatus::New, OrderStatus::Preparing]))->exists();

        if ($pending) {
            return back()->with('error', __('dinein.station_busy', ['name' => $station->name]));
        }

        $station->delete();

        return redirect()->route('tenant.admin.restaurant.capacity.index')->with('status', __('dinein.station_deleted'));
    }

    /** @return array{name: string} */
    private function validated(Request $request, ?Station $station = null, ?string $bag = null): array
    {
        return $request->validateWithBag($bag ?? 'default', [
            'name' => ['required', 'string', 'max:60', Rule::unique('dinein_stations')->where('restaurant_id', tenant()->getKey())->ignore($station)],
        ], ['name.unique' => __('dinein.station_taken')]);
    }
}
