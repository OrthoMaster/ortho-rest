<?php

namespace App\Modules\Menu\Http;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $menu = in_array($request->query('menu'), ['dine_in', 'takeaway'], true) ? $request->query('menu') : 'all';

        $categories = MenuCategory::with(['items' => fn ($query) => $query
            ->when($menu !== 'all', fn ($query) => $query->whereNotNull("price_$menu"))])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('modules.menu.index', compact('categories', 'menu'));
    }

    public function create(Request $request): View
    {
        return view('modules.menu.form', [
            'item' => new MenuItem(['menu_category_id' => $request->query('category'), 'is_visible' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        MenuItem::create([
            ...$data,
            'image_path' => $this->storeImage($request),
            'sort_order' => SortOrder::next(MenuItem::where('menu_category_id', $data['menu_category_id'])),
        ]);

        return redirect()->route('tenant.admin.menu.index')->with('status', __('menu.item_saved'));
    }

    public function edit(MenuItem $item): View
    {
        return view('modules.menu.form', ['item' => $item, 'categories' => $this->categories()]);
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $this->deleteImage($item);
            $data['image_path'] = $this->storeImage($request);
        }

        $item->update($data);

        return redirect()->route('tenant.admin.menu.index')->with('status', __('menu.item_saved'));
    }

    public function toggle(Request $request, MenuItem $item): RedirectResponse
    {
        $field = $request->validate(['field' => ['required', Rule::in(['is_sold_out', 'is_visible'])]])['field'];

        $item->update([$field => ! $item->{$field}]);

        return back();
    }

    public function move(Request $request, MenuItem $item): RedirectResponse
    {
        SortOrder::move(
            MenuItem::where('menu_category_id', $item->menu_category_id),
            $item,
            $request->input('direction') === 'up' ? -1 : 1,
        );

        return back();
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $this->deleteImage($item);
        $item->delete();

        return redirect()->route('tenant.admin.menu.index')->with('status', __('menu.item_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'menu_category_id' => ['required', Rule::exists('menu_categories', 'id')->where('restaurant_id', tenant()->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_dine_in' => ['nullable', 'integer', 'min:0', 'max:999999999', 'required_without:price_takeaway'],
            'price_takeaway' => ['nullable', 'integer', 'min:0', 'max:999999999', 'required_without:price_dine_in'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        unset($data['image']);

        return [
            ...$data,
            'is_sold_out' => $request->boolean('is_sold_out'),
            'is_visible' => $request->boolean('is_visible'),
        ];
    }

    private function storeImage(Request $request): ?string
    {
        return $request->file('image')?->store(
            'restaurants/'.tenant()->getKey().'/menu',
            config('liresto.uploads_disk'),
        ) ?: null;
    }

    private function deleteImage(MenuItem $item): void
    {
        if ($item->image_path) {
            Storage::disk(config('liresto.uploads_disk'))->delete($item->image_path);
        }
    }

    private function categories()
    {
        return MenuCategory::orderBy('sort_order')->orderBy('id')->get();
    }
}
