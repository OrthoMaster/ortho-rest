<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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
