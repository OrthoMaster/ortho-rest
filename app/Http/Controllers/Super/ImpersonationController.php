<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImpersonationController extends Controller
{
    public function start(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $owner = $restaurant->owner();

        if ($owner === null) {
            return back()->with('error', __('ui.super.no_owner'));
        }

        $admin = $request->user();

        Log::info('Impersonate start', ['admin' => $admin->id, 'user' => $owner->id, 'restaurant' => $restaurant->id]);

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $admin->id);

        return redirect()->route('tenant.admin.dashboard', ['resto' => $restaurant->slug]);
    }

    public function leave(Request $request): RedirectResponse
    {
        $admin = User::find($request->session()->pull('impersonator_id'));

        abort_unless($admin?->is_super_admin, 403);

        Log::info('Impersonate stop', ['admin' => $admin->id, 'user' => $request->user()->id]);

        $restaurant = $request->user()->restaurant;

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('super.restaurants.show', $restaurant);
    }
}
