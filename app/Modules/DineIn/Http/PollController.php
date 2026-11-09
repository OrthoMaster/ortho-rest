<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Modules\DineIn\Duty;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trang quản lý gọi mỗi vài giây để báo:
 * - người phục vụ: món đã xong của bàn mình nhận đơn hoặc khu vực mình được phân công;
 * - người ở bếp, bar: món mới gọi ở khu mình đang làm.
 */
class PollController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $ready = [];

        if ($user->can('dinein.order')) {
            $areas = Duty::areaIds($user);

            $ready = OrderLine::with('diningTable')
                ->where('status', LineStatus::Done)
                ->whereHas('diningTable', fn (Builder $query) => $query->whereNull('closed_at'))
                ->where(fn (Builder $query) => $query->where('ordered_by', $user->id)
                    ->when($areas, fn (Builder $query) => $query->orWhereHas('diningTable', fn (Builder $query) => $query->whereIn('area_id', $areas))))
                ->orderBy('done_at')
                ->limit(50)
                ->get()
                ->map(fn (OrderLine $line) => [
                    'id' => $line->id,
                    'table' => $line->diningTable->label,
                    'text' => $line->quantity.' × '.$line->name,
                    'detail' => $line->optionText(),
                    'serve_url' => route('tenant.admin.dinein.lines.serve', $line),
                    'table_url' => route('tenant.admin.dinein.tables.show', $line->diningTable->label),
                ])
                ->all();
        }

        $stationIds = Duty::stationIds($user);
        $fallback = Station::fallback()?->id;
        $stations = Station::whereKey($stationIds)->orderBy('sort_order')->orderBy('id')->get()->map(fn (Station $station) => [
            'id' => $station->id,
            'name' => $station->name,
            'url' => route('tenant.admin.dinein.screens.show', $station),
            'ids' => OrderLine::where('status', LineStatus::New)
                ->where(fn (Builder $query) => $query->where('dinein_station_id', $station->id)
                    ->when($station->id === $fallback, fn (Builder $query) => $query->orWhereNull('dinein_station_id')))
                ->pluck('id')
                ->all(),
        ])->all();

        return response()->json(['ready' => $ready, 'stations' => $stations]);
    }
}
