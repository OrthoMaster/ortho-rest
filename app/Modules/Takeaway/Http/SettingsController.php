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
            'order_code_prefix' => ['sometimes', 'required', 'regex:/^[A-Za-z]$/'],
        ], ['order_code_prefix.regex' => __('takeaway.order_code_prefix_invalid')]);

        $prefix = $data['order_code_prefix'] ?? null;
        unset($data['order_code_prefix']);

        $settings = [...$data, 'accepting_orders' => $request->boolean('accepting_orders')];

        // Đổi chữ đầu mã đơn chỉ chủ quán làm. Quyền giảm giá trực tiếp nay giao theo chức vụ.
        if ($request->user()->isOwner() && $prefix !== null) {
            $settings['order_code_prefix'] = strtoupper($prefix);
        }

        $this->modules->updateSettings(tenant(), 'takeaway', $settings);

        return back()->with('status', __('takeaway.settings_saved'));
    }
}
