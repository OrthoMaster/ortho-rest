<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Như can:..., nhưng chỉ cần một trong các quyền. */
class AuthorizeAny
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        abort_unless(Gate::any($abilities), 403);

        return $next($request);
    }
}
