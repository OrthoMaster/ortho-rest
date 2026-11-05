<?php

namespace App\Http\Middleware;

use App\Models\RestaurantDomain;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantByHost
{
    public function __construct(private TenantContext $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $domain = RestaurantDomain::verified()->where('domain', $host)->with('restaurant')->first();

        abort_if($domain === null, 404);

        $this->tenants->set($domain->restaurant);

        $request->route()->forgetParameter('customDomain');
        URL::defaults(['customDomain' => $host]);

        return $next($request);
    }
}
