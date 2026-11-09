<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\DineIn\Models\Station;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
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
    public function index(): View
    {
        return view('modules.dinein.stations', [
            'stations' => Station::withCount('items')->orderBy('sort_order')->orderBy('id')->get(),
            'unassigned' => MenuItem::whereNotNull('price_dine_in')->whereNull('dinein_station_id')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $station = Station::create([...$this->validated($request), 'sort_order' => SortOrder::next(Station::query())]);

        return redirect()->route('tenant.admin.dinein.stations.edit', $station)->with('status', __('dinein.station_created'));
    }

    public function edit(Station $station): View
    {
        return view('modules.dinein.station-form', [
            'station' => $station,
            'isFallback' => Station::fallback()?->is($station),
            'categories' => MenuCategory::with(['items' => fn ($query) => $query->whereNotNull('price_dine_in')->with('dineinStation')])
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

            $before = $station->items()->orderBy('id')->pluck('name', 'id');
            // Bỏ chọn thì món về "chưa chọn khu" (khu đầu tiên làm); chọn thì chuyển về khu này.
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

        return redirect()->route('tenant.admin.dinein.stations.index')->with('status', __('dinein.station_saved'));
    }

    public function reorder(Request $request): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::reorder(Station::query(), array_map('intval', $ids));

        return response()->noContent();
    }

    /** Món của khu bị xoá về "chưa chọn khu"; món đang chờ vẫn hiện ở khu đầu tiên. */
    public function destroy(Station $station): RedirectResponse
    {
        if (Station::count() === 1) {
            return back()->with('error', __('dinein.station_last'));
        }

        $station->delete();

        return redirect()->route('tenant.admin.dinein.stations.index')->with('status', __('dinein.station_deleted'));
    }

    /** @return array{name: string} */
    private function validated(Request $request, ?Station $station = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('dinein_stations')->where('restaurant_id', tenant()->getKey())->ignore($station)],
        ], ['name.unique' => __('dinein.station_taken')]);
    }
}
