<?php

namespace App\Modules\Takeaway\Http;

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

        $this->modules->updateSettings(tenant(), 'takeaway', [
            ...$data,
            'accepting_orders' => $request->boolean('accepting_orders'),
        ]);

        return back()->with('status', __('takeaway.settings_saved'));
    }
}
