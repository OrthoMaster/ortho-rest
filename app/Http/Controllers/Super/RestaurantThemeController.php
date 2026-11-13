<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Tenancy\RestaurantTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestaurantThemeController extends Controller
{
    public function update(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate(['primary' => RestaurantTheme::RULE, 'secondary' => RestaurantTheme::RULE]);

        RestaurantTheme::save($restaurant, $data['primary'], $data['secondary']);

        return back()->with('status', __('ui.super.theme_saved'));
    }

    public function destroy(Restaurant $restaurant): RedirectResponse
    {
        RestaurantTheme::reset($restaurant);

        return back()->with('status', __('ui.super.theme_reset'));
    }
}
