<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 301 từ domain-chinh/{slug}/... sang domain riêng khi quán đã có domain đã xác minh. */
class RedirectToCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() || ! ($domain = tenant()->primaryDomain())) {
            return $next($request);
        }

        $path = substr($request->getPathInfo(), strlen('/'.tenant()->slug));
        $query = $request->getQueryString();
        $port = in_array($request->getPort(), [80, 443], true) ? '' : ':'.$request->getPort();

        return redirect()->away(
            $request->getScheme().'://'.$domain.$port.($path === '' ? '/' : $path).($query ? "?$query" : ''),
            301,
        );
    }
}
