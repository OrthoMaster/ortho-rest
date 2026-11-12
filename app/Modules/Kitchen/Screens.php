<?php

namespace App\Modules\Kitchen;

use App\Models\Shift;
use App\Models\User;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use App\Modules\Schedule\Duty;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\Models\TakeawayOrderItem;
use App\Modules\Takeaway\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Các màn hình khu vực một người mở được, số món chờ, ca đang diễn ra. Dùng ở Màn hình khu vực và Tổng quan. */
final class Screens
{
    /** @return Collection<int, Station> khu mở được: có quyền mở mọi khu, hoặc đang được phân công */
    public static function stationsFor(User $user): Collection
    {
        $ids = $user->can('kitchen.stations') ? null : Duty::stationIds($user);

        return Station::when($ids !== null, fn (Builder $query) => $query->whereKey($ids))->orderBy('sort_order')->orderBy('id')->get();
    }

    /** Màn hình Đơn mang đi: khi bật Đặt mang đi, cho người đổi được trạng thái đơn mang đi. */
    public static function canPrep(User $user): bool
    {
        return has_module('takeaway') && $user->can('takeaway.status');
    }

    /** Đơn mang đi chưa giao, nhận hôm nay, theo giờ giao. */
    public static function prepOrders(): Builder
    {
        return TakeawayOrder::whereIn('status', [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready])
            ->whereDate('pickup_date', today())
            ->orderBy('pickup_at');
    }

    /** @return array<int, int> số món đang chờ của từng khu (tại bàn và mang đi) */
    public static function pendingCounts(): array
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

    /** Giờ bắt đầu ca đang diễn ra (kể cả ca từ hôm qua kéo qua nửa đêm), không có ca thì null. */
    public static function shiftStart(): ?CarbonImmutable
    {
        foreach (Duty::slots() as $slot) {
            $shift = $slot['shift'] ? Shift::find($slot['shift']) : null;

            if ($shift) {
                return CarbonImmutable::parse($slot['date'])->setTimeFromTimeString($shift->start_time);
            }
        }

        return null;
    }
}
