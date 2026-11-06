<?php

namespace App\Http\Controllers\Admin;

use App\Auth\DeviceGuard;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('admin.profile', [
            'user' => $user,
            'devices' => $user->devices()->latest('last_seen_at')->get(),
            'currentDevice' => $request->session()->get(DeviceGuard::SESSION_KEY),
            'impersonating' => $request->session()->has('impersonator_id'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
        ]);

        $user->update($data);

        return back()->with('status', __('ui.profile.saved'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', __('ui.profile.password_saved'));
    }
}
