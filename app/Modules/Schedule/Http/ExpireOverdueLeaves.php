<?php

namespace App\Modules\Schedule\Http;

use App\Modules\Schedule\LeaveBook;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Không có cron: mỗi lần mở trang Lịch làm việc thì các đơn đã đến giờ nghỉ mà vẫn chờ duyệt
 * được tự chuyển sang không duyệt.
 */
class ExpireOverdueLeaves
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && tenant() !== null) {
            LeaveBook::expireOverdue();
        }

        return $next($request);
    }
}
