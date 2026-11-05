<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class RestaurantThemeController extends Controller
{
    public function update(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate([
            'primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $restaurant->update(['theme' => [...($restaurant->theme ?? []), ...array_map('strtolower', $data)]]);

        return back()->with('status', __('ui.super.theme_saved'));
    }

    public function destroy(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update(['theme' => Arr::except($restaurant->theme ?? [], ['primary', 'secondary']) ?: null]);

        return back()->with('status', __('ui.super.theme_reset'));
    }
}
