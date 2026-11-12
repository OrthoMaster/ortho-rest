<?php

namespace App\Modules\Kitchen\Http;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use App\Modules\Schedule\Duty;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\Models\TakeawayOrderItem;
use App\Modules\Takeaway\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Module Màn hình khu. Màn hình của một khu (bếp, bar): món mới gọi tại bàn và món của đơn mang đi hôm nay,
 * bấm Xong từng món. Thêm màn hình Chuẩn bị đơn mang đi: nhận món đã xong, đóng gói, giao khách.
 */
class ScreenController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $stations = $this->stationsFor($request);
        $prep = $this->canPrep($request);

        if ($stations->count() + ($prep ? 1 : 0) === 1) {
            return $prep
                ? redirect()->route('tenant.admin.kitchen.takeaway')
                : redirect()->route('tenant.admin.kitchen.show', $stations->first());
        }

        return view('modules.kitchen.screens', [
            'stations' => $stations,
            'pending' => $this->pendingCounts(),
            'prep' => $prep,
            'prepCount' => $prep ? $this->prepOrders()->count() : 0,
        ]);
    }

    public function show(Request $request, Station $station): View
    {
        abort_unless(Duty::canOpenStation($request->user(), $station->id), 403);

        $data = [
            'station' => $station,
            'tickets' => $this->tickets($station),
            'recent' => $this->recent($station),
        ];

        // Màn hình tự tải lại phần danh sách món mỗi vài giây.
        if ($request->hasHeader('X-Board')) {
            return view('modules.kitchen.board', $data);
        }

        return view('modules.kitchen.screen', $data + ['stations' => $this->stationsFor($request), 'prep' => $this->canPrep($request)]);
    }

    public function done(Request $request, Station $station): RedirectResponse
    {
        abort_unless(Duty::canOpenStation($request->user(), $station->id), 403);

        $data = $request->validate([
            'lines' => ['array'], 'lines.*' => ['integer'],
            'takeaway' => ['array'], 'takeaway.*' => ['integer'],
        ]);
        $by = ['done_by' => $request->user()->id, 'done_at' => now()];

        $count = $this->lines($station)->whereKey($data['lines'] ?? [])->where('status', LineStatus::New)
            ->update(['status' => LineStatus::Done, ...$by]);

        if (($data['takeaway'] ?? []) !== [] && has_module('takeaway')) {
            $items = $this->takeawayLines($station)->whereKey($data['takeaway'])->get();
            $count += $items->sum(fn (TakeawayOrderItem $item) => $item->update($by) ? 1 : 0);

            // Bếp bắt đầu trả món thì đơn chuyển sang Đang làm.
            TakeawayOrder::whereKey($items->pluck('takeaway_order_id')->unique())->where('status', OrderStatus::New)
                ->update(['status' => OrderStatus::Preparing]);
        }

        return redirect()->route('tenant.admin.kitchen.show', $station)
            ->with('status', trans_choice('kitchen.done_count', $count, ['count' => $count]));
    }

    /** Chuẩn bị đơn mang đi: các đơn chưa giao, món nào đã xong. */
    public function takeaway(Request $request): View
    {
        abort_unless($this->canPrep($request), 403);

        $shiftStart = $this->shiftStart();
        $data = [
            'orders' => $this->prepOrders()->with(['items', 'customer'])->get(),
            'shiftStart' => $shiftStart,
            // Cột phải: đơn đã giao từ đầu ca (không có ca thì từ đầu ngày).
            'delivered' => TakeawayOrder::with(['items', 'customer'])->where('status', OrderStatus::Delivered)
                ->where('updated_at', '>=', $shiftStart ?? today())->latest('updated_at')->limit(50)->get(),
        ];

        if ($request->hasHeader('X-Board')) {
            return view('modules.kitchen.prep-board', $data);
        }

        return view('modules.kitchen.prep', $data + ['stations' => $this->stationsFor($request)]);
    }

    /** Đóng gói xong (Sẵn sàng), đã giao khách, hoặc huỷ đơn. */
    public function takeawayStatus(Request $request, TakeawayOrder $order): RedirectResponse
    {
        abort_unless($this->canPrep($request), 403);

        $status = OrderStatus::from($request->validate([
            'status' => ['required', Rule::in([OrderStatus::Ready->value, OrderStatus::Delivered->value, OrderStatus::Cancelled->value])],
        ])['status']);

        // Đơn mới đóng gói luôn: đi qua Đang làm cho đúng thứ tự trạng thái.
        if ($status === OrderStatus::Ready && $order->status === OrderStatus::New) {
            $order->update(['status' => OrderStatus::Preparing]);
        }

        if (! $order->status->canBecome($status)) {
            return back()->with('error', __('takeaway.bad_transition'));
        }

        $order->update(['status' => $status]);

        $message = match ($status) {
            OrderStatus::Ready => 'kitchen.packed',
            OrderStatus::Delivered => 'kitchen.delivered',
            default => 'kitchen.order_cancelled',
        };

        return redirect()->route('tenant.admin.kitchen.takeaway')->with('status', __($message, ['code' => $order->code()]));
    }

    /**
     * Phiếu của khu: mỗi bàn một phiếu (gộp các lượt gọi), mỗi đơn mang đi một phiếu.
     * Xếp theo giờ: bàn theo giờ gọi món đầu tiên còn chờ, đơn mang đi theo giờ giao.
     *
     * @return Collection<int, array{type: string, key: string, lines: Collection, at: mixed}>
     */
    private function tickets(Station $station): Collection
    {
        $tables = $this->lines($station)->where('status', LineStatus::New)->orderBy('id')->get()
            ->groupBy('dinein_table_id')
            ->map(fn (Collection $lines, $table) => ['type' => 'table', 'key' => 'b'.$table, 'lines' => $lines, 'at' => $lines->min('created_at')]);

        $orders = has_module('takeaway')
            ? $this->takeawayLines($station)->orderBy('id')->get()->groupBy('takeaway_order_id')
                ->map(fn (Collection $lines, $order) => ['type' => 'takeaway', 'key' => 'm'.$order, 'lines' => $lines, 'at' => $lines->first()->order->pickup_at])
            : collect();

        return $tables->values()->concat($orders->values())->sortBy(fn (array $ticket) => $ticket['at']->getTimestamp())->values();
    }

    /** Món vừa xong 30 phút qua, cả tại bàn và mang đi. */
    private function recent(Station $station): Collection
    {
        $since = now()->subMinutes(30);

        $tables = $this->lines($station)->where('status', '!=', LineStatus::New)->whereNotNull('done_at')
            ->where('done_at', '>=', $since)->latest('done_at')->limit(8)->get()
            ->map(fn (OrderLine $line) => ['where' => $line->diningTable->label, 'text' => $line->quantity.' × '.$line->name, 'badge' => $line->status->badge(), 'status' => $line->status->label(), 'at' => $line->done_at]);

        $orders = has_module('takeaway')
            ? $this->stationScope(TakeawayOrderItem::with('order'), $station)->where('done_at', '>=', $since)->latest('done_at')->limit(8)->get()
                ->map(fn (TakeawayOrderItem $item) => ['where' => __('kitchen.takeaway_tag').' '.$item->order->code(), 'text' => $item->quantity.' × '.$item->name, 'badge' => 'badge-ok', 'status' => __('kitchen.item_done'), 'at' => $item->done_at])
            : collect();

        return $tables->concat($orders)->sortByDesc(fn (array $row) => $row['at']->getTimestamp())->take(8)->values();
    }

    /** Món tại bàn của khu. */
    private function lines(Station $station): Builder
    {
        return $this->stationScope(OrderLine::with('diningTable', 'orderedBy'), $station);
    }

    /** Món mang đi còn chờ của khu: đơn Mới hoặc Đang làm, nhận hôm nay. */
    private function takeawayLines(Station $station): Builder
    {
        return $this->stationScope(TakeawayOrderItem::with('order.customer'), $station)
            ->whereNull('done_at')
            ->whereHas('order', fn (Builder $query) => $query->whereIn('status', [OrderStatus::New, OrderStatus::Preparing])
                ->whereDate('pickup_date', today()));
    }

    /** Món chưa chọn khu không vào màn hình nào (đã tự xong khi gọi). */
    private function stationScope(Builder $query, Station $station): Builder
    {
        return $query->where('dinein_station_id', $station->id);
    }

    /** Đơn mang đi chưa giao, nhận hôm nay, theo giờ giao. */
    private function prepOrders(): Builder
    {
        return TakeawayOrder::whereIn('status', [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready])
            ->whereDate('pickup_date', today())
            ->orderBy('pickup_at');
    }

    /** Giờ bắt đầu ca đang diễn ra hôm nay (kể cả ca từ hôm qua kéo qua nửa đêm), không có ca thì null. */
    private function shiftStart(): ?CarbonImmutable
    {
        foreach (Duty::slots() as $slot) {
            $shift = $slot['shift'] ? Shift::find($slot['shift']) : null;

            if ($shift) {
                return CarbonImmutable::parse($slot['date'])->setTimeFromTimeString($shift->start_time);
            }
        }

        return null;
    }

    private function canPrep(Request $request): bool
    {
        return has_module('takeaway') && $request->user()->can('takeaway.status');
    }

    /** @return Collection<int, Station> */
    private function stationsFor(Request $request): Collection
    {
        $user = $request->user();
        $ids = $user->can('kitchen.stations') ? null : Duty::stationIds($user);

        return Station::when($ids !== null, fn (Builder $query) => $query->whereKey($ids))->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @return array<int, int> số món đang chờ của từng khu (tại bàn và mang đi) */
    private function pendingCounts(): array
    {
        $rows = OrderLine::where('status', LineStatus::New)
            ->selectRaw('dinein_station_id, sum(quantity) as total')
            ->groupBy('dinein_station_id')
            ->get();

        if (has_module('takeaway')) {
            $rows = $rows->concat(TakeawayOrderItem::whereNull('done_at')
                ->whereHas('order', fn (Builder $query) => $query->whereIn('status', [OrderStatus::New, OrderStatus::Preparing])->whereDate('pickup_date', today()))
                ->selectRaw('dinein_station_id, sum(quantity) as total')
                ->groupBy('dinein_station_id')
                ->get());
        }

        return $rows->whereNotNull('dinein_station_id')->reduce(function (array $counts, $row) {
            $counts[$row->dinein_station_id] = ($counts[$row->dinein_station_id] ?? 0) + (int) $row->total;

            return $counts;
        }, []);
    }
}
