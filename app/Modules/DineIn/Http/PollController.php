<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use App\Modules\Schedule\Duty;
use App\Modules\Takeaway\Models\TakeawayOrderItem;
use App\Modules\Takeaway\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trang quản lý gọi mỗi vài giây để báo:
 * - người phục vụ: món đã xong ở khu vực bàn mình được phân công phục vụ trong ca này; khu vực
 *   không có ai được phân công (hoặc tắt Lịch làm việc) thì báo cho người đã gọi món;
 * - người ở bếp, bar: món mới gọi ở khu mình đang làm.
 */
class PollController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $ready = [];

        if (has_module('dinein') && $user->can('dinein.order')) {
            $areas = Duty::areaIds($user);
            $staffed = Duty::staffedAreaIds();

            $ready = OrderLine::with('diningTable')
                ->where('status', LineStatus::Done)
                ->whereHas('diningTable', fn (Builder $query) => $query->whereNull('closed_at'))
                ->where(fn (Builder $query) => $query
                    ->whereHas('diningTable', fn (Builder $query) => $query->whereIn('area_id', $areas ?: [0]))
                    ->orWhere(fn (Builder $query) => $query->where('ordered_by', $user->id)
                        ->whereHas('diningTable', fn (Builder $query) => $query->whereNotIn('area_id', $staffed ?: [0]))))
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

        // Màn hình khu: người đang làm ở bếp, bar nhận báo món mới (tại bàn và mang đi) của khu mình.
        $stations = [];

        if (has_module('kitchen')) {
            $takeaway = has_module('takeaway');
            $stations = Station::whereKey(Duty::stationIds($user))->orderBy('sort_order')->orderBy('id')->get()->map(fn (Station $station) => [
                'id' => $station->id,
                'name' => $station->name,
                'url' => route('tenant.admin.kitchen.show', $station),
                'ids' => OrderLine::where('status', LineStatus::New)->where('dinein_station_id', $station->id)->pluck('id')
                    // Món mang đi khoá có chữ t để không trùng với món tại bàn.
                    ->concat($takeaway ? TakeawayOrderItem::whereNull('done_at')->where('dinein_station_id', $station->id)
                        ->whereHas('order', fn (Builder $query) => $query->whereIn('status', [OrderStatus::New, OrderStatus::Preparing])->whereDate('pickup_date', today()))
                        ->pluck('id')->map(fn (int $id) => 't'.$id) : [])
                    ->all(),
            ])->all();
        }

        return response()->json(['ready' => $ready, 'stations' => $stations]);
    }
}
