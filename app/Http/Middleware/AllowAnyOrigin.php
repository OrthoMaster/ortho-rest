<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Địa chỉ JSON của trang đặt bàn: trang quán tự tạo chạy cô lập (nguồn "null") nên
 * gọi như từ nơi khác. Không dùng cookie nên cho mọi nguồn gọi.
 */
class AllowAnyOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
