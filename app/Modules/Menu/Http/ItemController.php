<?php

namespace App\Modules\Menu\Http;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuTag;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    private const NEW_CATEGORY = 'new';

    public function index(Request $request): View
    {
        $menu = in_array($request->query('menu'), ['dine_in', 'takeaway'], true) ? $request->query('menu') : 'all';

        $categories = MenuCategory::with(['items' => fn ($query) => $query
            ->with('tags')
            ->when($menu !== 'all', fn ($query) => $query->whereNotNull("price_$menu"))])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('modules.menu.index', compact('categories', 'menu'));
    }

    public function create(Request $request): View
    {
        return $this->form(new MenuItem(['menu_category_id' => $request->query('category'), 'is_visible' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $tags] = $this->validated($request);

        DB::transaction(function () use ($request, $data, $tags): void {
            $data['menu_category_id'] = $this->categoryId($request, $data['menu_category_id']);

            MenuItem::create([
                ...$data,
                'image_path' => $this->storeImage($request),
                'sort_order' => SortOrder::next(MenuItem::where('menu_category_id', $data['menu_category_id'])),
            ])->syncTags($tags);
        });

        return redirect()->route('tenant.admin.menu.index')->with('status', __('menu.item_saved'));
    }

    public function edit(MenuItem $item): View
    {
        return $this->form($item);
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        [$data, $tags] = $this->validated($request);

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $this->deleteImage($item);
            $data['image_path'] = $this->storeImage($request);
        }

        DB::transaction(function () use ($request, $item, $data, $tags): void {
            $data['menu_category_id'] = $this->categoryId($request, $data['menu_category_id']);

            $item->update($data);
            $item->syncTags($tags);
        });

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

    private function form(MenuItem $item): View
    {
        return view('modules.menu.form', [
            'item' => $item,
            'categories' => MenuCategory::orderBy('sort_order')->orderBy('id')->get(),
            'tags' => MenuTag::orderBy('id')->get(),
        ]);
    }

    /** Chọn "Danh mục mới" thì tạo ngay, trùng tên thì dùng lại danh mục đã có. */
    private function categoryId(Request $request, int|string $selected): int
    {
        if ($selected !== self::NEW_CATEGORY) {
            return (int) $selected;
        }

        return MenuCategory::firstOrCreate(
            ['name' => trim($request->input('new_category_name'))],
            ['sort_order' => SortOrder::next(MenuCategory::query())],
        )->getKey();
    }

    /** @return array{array<string, mixed>, list<int>} */
    private function validated(Request $request): array
    {
        $restaurantId = tenant()->getKey();

        $data = $request->validate([
            'menu_category_id' => [
                'required',
                Rule::when(
                    $request->input('menu_category_id') !== self::NEW_CATEGORY,
                    [Rule::exists('menu_categories', 'id')->where('restaurant_id', $restaurantId)],
                ),
            ],
            'new_category_name' => ['nullable', 'required_if:menu_category_id,'.self::NEW_CATEGORY, 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_dine_in' => ['nullable', 'integer', 'min:0', 'max:999999999', 'required_without:price_takeaway'],
            'price_takeaway' => ['nullable', 'integer', 'min:0', 'max:999999999', 'required_without:price_dine_in'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('menu_tags', 'id')->where('restaurant_id', $restaurantId)],
        ]);

        $tags = array_map('intval', $data['tags'] ?? []);

        unset($data['image'], $data['tags'], $data['new_category_name']);

        return [[
            ...$data,
            'is_sold_out' => $request->boolean('is_sold_out'),
            'is_visible' => $request->boolean('is_visible'),
        ], $tags];
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
}
