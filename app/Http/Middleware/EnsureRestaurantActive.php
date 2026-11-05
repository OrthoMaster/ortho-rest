<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Quán bị khoá thì web và phần quản lý đều dừng. Super admin đăng nhập thay vẫn vào để hỗ trợ. */
class EnsureRestaurantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenant()->isLocked() && ! $request->session()->has('impersonator_id')) {
            return response()->view('public.locked', status: 403);
        }

        return $next($request);
    }
}
