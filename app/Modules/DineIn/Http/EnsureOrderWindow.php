<?php

namespace App\Modules\DineIn\Http;

use App\Modules\DineIn\OrderWindow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ngoài giờ làm thì không mở bàn, gọi món, đóng bàn được: quay về trang Bàn kèm lý do. */
class EnsureOrderWindow
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($reason = OrderWindow::blocked($request->user())) {
            return $request->expectsJson()
                ? response()->json(['message' => $reason], 403)
                : redirect()->route('tenant.admin.dinein.tables.index')->with('warning', $reason);
        }

        return $next($request);
    }
}
