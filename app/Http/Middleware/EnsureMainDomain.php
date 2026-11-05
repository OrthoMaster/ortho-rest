<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Đăng nhập, super admin và quản lý quán chỉ chạy trên domain chính. */
class EnsureMainDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array($request->getHost(), config('liresto.main_domains'), true), 404);

        return $next($request);
    }
}
