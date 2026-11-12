<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    /** Nhiều module (module:dinein,kitchen) thì chỉ cần bật một trong số đó. */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        abort_unless(collect($modules)->contains(fn (string $module) => has_module($module)), 404);

        return $next($request);
    }
}
