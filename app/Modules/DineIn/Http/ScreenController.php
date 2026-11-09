<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Modules\DineIn\Duty;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Màn hình của một khu (bếp, bar): món mới gọi, bấm Xong để báo người phục vụ. */
class ScreenController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $stations = $this->stationsFor($request);

        if ($stations->count() === 1) {
            return redirect()->route('tenant.admin.dinein.screens.show', $stations->first());
        }

        return view('modules.dinein.screens', ['stations' => $stations, 'pending' => $this->pendingCounts()]);
    }

    public function show(Request $request, Station $station): View
    {
        abort_unless(Duty::canOpenStation($request->user(), $station->id), 403);

        $pending = $this->lines($station)->where('status', LineStatus::New)->orderBy('id')->get();
        $data = [
            'station' => $station,
            // Một phiếu là các món của một bàn trong một lần gọi.
            'tickets' => $pending->groupBy(fn (OrderLine $line) => $line->dinein_table_id.'-'.$line->round),
            'recent' => $this->lines($station)->where('status', '!=', LineStatus::New)->whereNotNull('done_at')
                ->where('done_at', '>=', now()->subMinutes(30))->latest('done_at')->limit(8)->get(),
        ];

        // Màn hình tự tải lại phần danh sách món mỗi vài giây.
        if ($request->hasHeader('X-Board')) {
            return view('modules.dinein.board', $data);
        }

        return view('modules.dinein.screen', $data + ['stations' => $this->stationsFor($request)]);
    }

    public function done(Request $request, Station $station): RedirectResponse
    {
        abort_unless(Duty::canOpenStation($request->user(), $station->id), 403);

        $ids = $request->validate(['lines' => ['required', 'array'], 'lines.*' => ['integer']])['lines'];

        $count = $this->lines($station)->whereKey($ids)->where('status', LineStatus::New)
            ->update(['status' => LineStatus::Done, 'done_by' => $request->user()->id, 'done_at' => now()]);

        return redirect()->route('tenant.admin.dinein.screens.show', $station)
            ->with('status', trans_choice('dinein.done_count', $count, ['count' => $count]));
    }

    /** Món của khu; khu đầu tiên nhận thêm món chưa chọn khu. */
    private function lines(Station $station): Builder
    {
        $fallback = Station::fallback()?->is($station);

        return OrderLine::with('diningTable', 'orderedBy')
            ->where(fn (Builder $query) => $query->where('dinein_station_id', $station->id)
                ->when($fallback, fn (Builder $query) => $query->orWhereNull('dinein_station_id')));
    }

    /** @return Collection<int, Station> */
    private function stationsFor(Request $request): Collection
    {
        $user = $request->user();
        $ids = $user->can('dinein.stations') ? null : Duty::stationIds($user);

        return Station::when($ids !== null, fn (Builder $query) => $query->whereKey($ids))->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @return array<int, int> số món đang chờ của từng khu */
    private function pendingCounts(): array
    {
        $fallback = Station::fallback()?->id;

        return OrderLine::where('status', LineStatus::New)
            ->selectRaw('dinein_station_id, sum(quantity) as total')
            ->groupBy('dinein_station_id')
            ->get()
            ->reduce(function (array $counts, $row) use ($fallback) {
                $key = $row->dinein_station_id ?? $fallback;
                $counts[$key] = ($counts[$key] ?? 0) + (int) $row->total;

                return $counts;
            }, []);
    }
}
