<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RestaurantDomainController extends Controller
{
    public function store(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->merge(['domain' => RestaurantDomain::normalize((string) $request->input('domain'))]);

        $data = $request->validate([
            'domain' => [
                'required', 'string', 'max:190',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9-]*[a-z]+$/',
                Rule::notIn(config('liresto.main_domains')),
                Rule::unique('restaurant_domains', 'domain'),
            ],
        ]);

        $restaurant->domains()->create($data);

        return back()->with('status', __('ui.super.domain_added'));
    }

    public function verify(Restaurant $restaurant, RestaurantDomain $domain): RedirectResponse
    {
        $domain->update(['verified_at' => now()]);

        return back()->with('status', __('ui.super.domain_verified'));
    }

    public function destroy(Restaurant $restaurant, RestaurantDomain $domain): RedirectResponse
    {
        $domain->delete();

        return back()->with('status', __('ui.super.domain_removed'));
    }
}
