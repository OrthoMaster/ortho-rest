<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cashier\Cashier;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\Kitchen\Screens;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationStatus;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        // Bật Màn hình khu vực: hiện các màn hình người này mở được để vào nhanh.
        $screens = has_module('kitchen') && $user->can('kitchen.screen');

        return view('admin.dashboard', [
            'restaurant' => tenant(),
            'user' => $user,
            'service' => $this->serviceTiles($user),
            'screens' => $screens,
            'stations' => $screens ? Screens::stationsFor($user) : collect(),
            'pending' => $screens ? Screens::pendingCounts() : [],
            'prep' => $screens && Screens::canPrep($user),
            'prepCount' => $screens && Screens::canPrep($user) ? Screens::prepOrders()->count() : 0,
        ]);
    }

    /**
     * Vào nhanh các màn hình phục vụ hằng ngày (chỉ module đang bật và người này có quyền), kèm số việc đang chờ.
     *
     * @return list<array{route: string, label: string, count: int, note: string}>
     */
    private function serviceTiles(User $user): array
    {
        $tiles = [];

        if (has_module('dinein') && $user->can('dinein.order')) {
            $count = DiningTable::open()->count();
            $tiles[] = ['route' => 'dinein.tables.index', 'label' => __('ui.dashboard.dinein'), 'count' => $count, 'note' => trans_choice('ui.dashboard.dinein_count', $count)];
        }

        if (has_module('reservation') && $user->can('reservation.view')) {
            $count = Reservation::whereDate('reserved_at', today())->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])->count();
            $tiles[] = ['route' => 'reservation.index', 'label' => __('ui.dashboard.reservation'), 'count' => $count, 'note' => trans_choice('ui.dashboard.reservation_count', $count)];
        }

        if (has_module('takeaway') && $user->can('takeaway.view')) {
            $count = TakeawayOrder::whereDate('pickup_at', today())->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])->count();
            $tiles[] = ['route' => 'takeaway.index', 'label' => __('ui.dashboard.takeaway'), 'count' => $count, 'note' => trans_choice('ui.dashboard.takeaway_count', $count)];
        }

        if (has_module('cashier') && (has_module('dinein') || has_module('takeaway')) && $user->canAny(['cashier.collect', 'cashier.reconcile'])) {
            $count = Cashier::pending()->count();
            $tiles[] = ['route' => 'cashier.index', 'label' => __('ui.dashboard.cashier'), 'count' => $count, 'note' => trans_choice('ui.dashboard.cashier_count', $count)];
        }

        return $tiles;
    }
}
