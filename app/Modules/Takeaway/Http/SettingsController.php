<?php

namespace App\Modules\Takeaway\Http;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private ModuleManager $modules) {}

    public function edit(): View
    {
        return view('modules.takeaway.settings', ['settings' => $this->modules->settings(tenant(), 'takeaway')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pickup_opens' => ['required', 'date_format:H:i'],
            'pickup_closes' => ['required', 'date_format:H:i', 'after:pickup_opens'],
        ]);

        $settings = [...$data, 'accepting_orders' => $request->boolean('accepting_orders')];

        // Quản lý không được tự cấp quyền, chỉ chủ quán đổi mục này.
        if ($request->user()->role === UserRole::Owner) {
            $settings['direct_discount_roles'] = array_values(array_intersect(
                (array) $request->input('direct_discount_roles', []),
                [UserRole::Manager->value, UserRole::Staff->value],
            ));
        }

        $this->modules->updateSettings(tenant(), 'takeaway', $settings);

        return back()->with('status', __('takeaway.settings_saved'));
    }
}
