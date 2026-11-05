<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()->restaurant_id === tenant()->getKey(), 403);

        return $next($request);
    }
}
