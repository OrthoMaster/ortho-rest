<?php

namespace App\Modules\Menu\Http;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\MenuCategory;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        MenuCategory::create([...$data, 'sort_order' => SortOrder::next(MenuCategory::query())]);

        return back()->with('status', __('menu.category_saved'));
    }

    public function update(Request $request, MenuCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_visible' => ['boolean'],
        ]);

        $category->update(['name' => $data['name'], 'is_visible' => $request->boolean('is_visible')]);

        return back()->with('status', __('menu.category_saved'));
    }

    public function move(Request $request, MenuCategory $category): RedirectResponse
    {
        SortOrder::move(MenuCategory::query(), $category, $request->input('direction') === 'up' ? -1 : 1);

        return back();
    }

    public function destroy(MenuCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return back()->with('error', __('menu.category_not_empty'));
        }

        $category->delete();

        return back()->with('status', __('menu.category_deleted'));
    }
}
