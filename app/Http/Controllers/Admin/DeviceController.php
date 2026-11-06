<?php

namespace App\Http\Controllers\Admin;

use App\Auth\DeviceGuard;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private DeviceGuard $devices) {}

    public function destroy(Request $request, int $device): RedirectResponse
    {
        $device = $request->user()->devices()->findOrFail($device);

        if ($device->getKey() === $request->session()->get(DeviceGuard::SESSION_KEY)) {
            return back()->with('error', __('ui.devices.current_hint'));
        }

        $this->devices->revoke($device);

        return back()->with('status', __('ui.devices.revoked_done', ['name' => $device->name]));
    }

    public function lock(Request $request): RedirectResponse
    {
        $locked = $request->boolean('devices_locked');

        $request->user()->forceFill(['devices_locked' => $locked])->save();

        return back()->with('status', __($locked ? 'ui.devices.locked_on' : 'ui.devices.locked_off'));
    }
}
