<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantBySlug
{
    public function __construct(private TenantContext $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('resto');
        $restaurant = Restaurant::where('slug', $slug)->first();

        abort_if($restaurant === null, 404);

        $this->tenants->set($restaurant);

        // Controller không cần nhận {resto}, route() trong quán tự điền slug.
        $request->route()->forgetParameter('resto');
        URL::defaults(['resto' => $slug]);

        return $next($request);
    }
}
