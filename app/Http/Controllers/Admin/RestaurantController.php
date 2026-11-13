<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\Kitchen\Kitchen;
use App\Modules\ModuleManager;
use App\Support\CustomerRetention;
use App\Tenancy\RestaurantLogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RestaurantController extends Controller
{
    public function edit(): View
    {
        return view('admin.restaurant.edit', ['restaurant' => tenant()]);
    }

    public function updateCustomerData(Request $request): RedirectResponse
    {
        $data = $request->validate(['customer_retention_days' => ['required', 'integer', Rule::in(CustomerRetention::DAYS)]]);

        tenant()->update($data);

        return back()->with('status', __('customers.retention_saved'));
    }

    /** Tiếng chuông báo món ở màn hình khu và báo món trên mọi trang. */
    public function updateSound(Request $request): RedirectResponse
    {
        $sound = $request->validate(['sound' => ['required', Rule::in(Kitchen::SOUNDS)]])['sound'];

        app(ModuleManager::class)->updateSettings(tenant(), 'kitchen', ['sound' => $sound]);
        app(ModuleManager::class)->forget(tenant());
        ActivityLog::record(tenant()->getKey(), 'settings', __('kitchen.log_sound', ['sound' => __('kitchen.sounds.'.$sound)]));

        return back()->with('status', __('kitchen.sound_saved'));
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => RestaurantLogo::RULES]);

        RestaurantLogo::store(tenant(), $request->file('logo'));
        ActivityLog::record(tenant()->getKey(), 'settings', __('activity.logo_changed'));

        return back()->with('status', __('ui.logo.saved'));
    }

    public function destroyLogo(): RedirectResponse
    {
        RestaurantLogo::remove(tenant());
        ActivityLog::record(tenant()->getKey(), 'settings', __('activity.logo_removed'));

        return back()->with('status', __('ui.logo.removed'));
    }
}
