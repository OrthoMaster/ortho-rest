<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Modules\ModuleDependencyException;
use App\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestaurantModuleController extends Controller
{
    public function update(Request $request, Restaurant $restaurant, string $module, ModuleManager $modules): RedirectResponse
    {
        abort_unless(array_key_exists($module, $modules->definitions()), 404);
        abort_if($modules->isCore($module) || ! $modules->isAvailable($module), 422);

        $name = fn (string $key): string => __($modules->definition($key)['name']);

        if (! $request->boolean('enabled')) {
            $disabled = $modules->disable($restaurant, $module);

            return back()->with('status', __('ui.super.modules_disabled', [
                'modules' => implode(', ', array_map($name, $disabled)),
            ]));
        }

        try {
            $modules->enable($restaurant, $module);
        } catch (ModuleDependencyException) {
            return back()->with('error', __('ui.super.module_needs', [
                'module' => $name($module),
                'modules' => implode(', ', array_map($name, $modules->missingDependencies($restaurant, $module))),
            ]));
        }

        return back()->with('status', __('ui.super.module_enabled', ['module' => $name($module)]));
    }
}
