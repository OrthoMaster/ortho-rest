<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Restaurant;
use App\Tenancy\RestaurantLogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestaurantLogoController extends Controller
{
    public function update(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->validate(['logo' => RestaurantLogo::RULES]);

        RestaurantLogo::store($restaurant, $request->file('logo'));
        ActivityLog::record($restaurant->getKey(), 'settings', __('activity.logo_changed'));

        return back()->with('status', __('ui.logo.saved'));
    }

    public function destroy(Restaurant $restaurant): RedirectResponse
    {
        RestaurantLogo::remove($restaurant);
        ActivityLog::record($restaurant->getKey(), 'settings', __('activity.logo_removed'));

        return back()->with('status', __('ui.logo.removed'));
    }
}
