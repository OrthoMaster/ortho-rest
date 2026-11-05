<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Tenancy\RestaurantLogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestaurantController extends Controller
{
    public function edit(): View
    {
        return view('admin.restaurant.edit', ['restaurant' => tenant()]);
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => RestaurantLogo::RULES]);

        RestaurantLogo::store(tenant(), $request->file('logo'));

        return back()->with('status', __('ui.logo.saved'));
    }

    public function destroyLogo(): RedirectResponse
    {
        RestaurantLogo::remove(tenant());

        return back()->with('status', __('ui.logo.removed'));
    }
}
