<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\RestaurantDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Caddy On-Demand TLS hỏi trước khi xin chứng chỉ: 200 thì cấp, khác 200 thì không. */
class CaddyDomainCheckController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $domain = strtolower((string) $request->query('domain'));
        $known = $domain !== '' && RestaurantDomain::verified()->where('domain', $domain)->exists();

        return response('', $known ? 200 : 404);
    }
}
