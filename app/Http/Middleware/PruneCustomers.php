<?php

namespace App\Http\Middleware;

use App\Support\CustomerRetention;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Không có cron: lần đầu trong ngày có người mở trang quản lý của quán thì
 * ẩn danh các khách đã quá thời hạn giữ thông tin.
 */
class PruneCustomers
{
    public function handle(Request $request, Closure $next): Response
    {
        $restaurant = tenant();

        if ($restaurant && Cache::add('customers-pruned:'.$restaurant->getKey().':'.today()->toDateString(), true, now()->endOfDay())) {
            CustomerRetention::prune($restaurant);
        }

        return $next($request);
    }
}
