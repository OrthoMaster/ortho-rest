<?php

namespace App\Modules\Menu\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuItemOptionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Tuỳ chọn khi gọi món, riêng từng món. Lưu là thay cả bộ nhóm của món; món đã
 * gọi giữ tên và giá tuỳ chọn lúc gọi nên không ảnh hưởng.
 */
class ItemOptionController extends Controller
{
    public function edit(MenuItem $item): View
    {
        return view('modules.menu.options', [
            'item' => $item->load('optionGroups.options'),
            // Món khác đã có tuỳ chọn, để sao chép cho nhanh.
            'sources' => MenuItem::whereKeyNot($item->getKey())->whereHas('optionGroups')->with('category')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $groups = $this->validated($request);

        DB::transaction(function () use ($item, $groups): void {
            $before = $this->summary($item);
            $item->optionGroups()->delete();

            foreach (array_values($groups) as $order => $group) {
                $this->createGroup($item, $group, $order);
            }

            $after = $this->summary($item->refresh());

            if ($before !== $after) {
                ActivityLog::record($item->restaurant_id, 'menu', __('menu.options_log', ['name' => $item->name]), $after !== [] ? $after : [__('menu.options_none')], $item);
            }
        });

        return redirect()->route('tenant.admin.menu.items.edit', $item)->with('status', __('menu.options_saved'));
    }

    /** Chép các nhóm của món khác vào cuối danh sách của món này; hai món giữ bản riêng. */
    public function copy(Request $request, MenuItem $item): RedirectResponse
    {
        $source = MenuItem::with('optionGroups.options')->findOrFail($request->validate(['from' => ['required', 'integer']])['from']);
        abort_if($source->is($item), 422);

        DB::transaction(function () use ($item, $source): void {
            $order = (int) $item->optionGroups()->max('sort_order') + 1;

            foreach ($source->optionGroups as $group) {
                $this->createGroup($item, [
                    'name' => $group->name,
                    'required' => $group->is_required,
                    'multiple' => $group->is_multiple,
                    'options' => $group->options->map(fn ($option) => ['name' => $option->name, 'price' => $option->price])->all(),
                ], $order++);
            }
        });

        return back()->with('status', __('menu.options_copied', ['name' => $source->name]));
    }

    private function createGroup(MenuItem $item, array $group, int $order): void
    {
        $created = $item->optionGroups()->create([
            'restaurant_id' => $item->restaurant_id,
            'name' => trim($group['name']),
            'is_required' => (bool) ($group['required'] ?? false),
            'is_multiple' => (bool) ($group['multiple'] ?? false),
            'sort_order' => $order,
        ]);

        foreach (array_values($group['options']) as $index => $option) {
            $created->options()->create([
                'restaurant_id' => $item->restaurant_id,
                'name' => trim($option['name']),
                'price' => (int) ($option['price'] ?? 0),
                'sort_order' => $index,
            ]);
        }
    }

    /** @return list<string> */
    private function summary(MenuItem $item): array
    {
        return $item->optionGroups()->with('options')->get()->map(fn (MenuItemOptionGroup $group) => $group->summary())->all();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'groups' => ['array', 'max:20'],
            'groups.*.name' => ['required', 'string', 'max:60'],
            'groups.*.required' => ['nullable', 'boolean'],
            'groups.*.multiple' => ['nullable', 'boolean'],
            'groups.*.options' => ['required', 'array', 'min:1', 'max:30'],
            'groups.*.options.*.name' => ['required', 'string', 'max:60'],
            'groups.*.options.*.price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], [
            'groups.*.options.required' => __('menu.options_need_one'),
        ], [
            'groups.*.name' => __('menu.option_group_name'),
            'groups.*.options.*.name' => __('menu.option_name'),
            'groups.*.options.*.price' => __('menu.option_price'),
        ])['groups'] ?? [];
    }
}
