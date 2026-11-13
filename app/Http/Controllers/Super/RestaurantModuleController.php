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
    /**
     * Lưu một lần cả danh sách bật / tắt: tắt trước (kéo theo module phụ thuộc), rồi bật theo thứ tự
     * trong config (module cha đứng trước module con). Module con mà module cha không bật thì bỏ qua và báo lại.
     */
    public function sync(Request $request, Restaurant $restaurant, ModuleManager $modules): RedirectResponse
    {
        $toggleable = array_keys(array_filter($modules->definitions(), fn ($definition, string $key) => ! $modules->isCore($key) && $modules->isAvailable($key), ARRAY_FILTER_USE_BOTH));
        $wanted = array_values(array_intersect($toggleable, (array) $request->input('modules', [])));
        $name = fn (string $key): string => __($modules->definition($key)['name']);
        $off = [];
        $on = [];
        $skipped = [];

        foreach ($toggleable as $key) {
            if (! in_array($key, $wanted, true) && $modules->isEnabled($restaurant, $key)) {
                $off = [...$off, ...$modules->disable($restaurant, $key)];
            }
        }

        foreach ($wanted as $key) {
            if ($modules->isEnabled($restaurant, $key)) {
                continue;
            }

            try {
                $modules->enable($restaurant, $key);
                $on[] = $key;
            } catch (ModuleDependencyException) {
                $skipped[] = $key;
            }
        }

        $response = back()->with('status', $on === [] && $off === []
            ? __('ui.super.modules_unchanged')
            : __('ui.super.modules_saved', ['on' => $on ? implode(', ', array_map($name, $on)) : '—', 'off' => $off ? implode(', ', array_map($name, array_unique($off))) : '—']));

        return $skipped ? $response->with('error', __('ui.super.modules_skipped', ['modules' => implode(', ', array_map($name, $skipped))])) : $response;
    }

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
