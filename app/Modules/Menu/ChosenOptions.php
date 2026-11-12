<?php

namespace App\Modules\Menu;

use App\Modules\Menu\Models\MenuItem;
use Closure;

/**
 * Kiểm tra tuỳ chọn khách chọn cho một món (nhóm bắt buộc, nhóm chỉ chọn một,
 * lựa chọn phải thuộc món) và chép lại tên, giá lúc gọi. Dùng cho Lấy đơn tại bàn và Đặt mang đi.
 */
final class ChosenOptions
{
    /**
     * @param  list<int>  $picked  id lựa chọn
     * @param  Closure(string): never  $fail
     * @param  string  $lang  nhóm chữ của module (dinein, takeaway)
     * @return list<array{id: int, group: string, name: string, price: int}>
     */
    public static function resolve(MenuItem $item, array $picked, Closure $fail, string $lang): array
    {
        $picked = array_values(array_unique(array_map('intval', $picked)));
        $chosen = [];

        foreach ($item->optionGroups as $group) {
            $inGroup = $group->options->filter(fn ($option) => in_array($option->id, $picked, true));

            if ($group->is_required && $inGroup->isEmpty()) {
                $fail(__("$lang.option_required", ['name' => $item->name, 'group' => $group->name]));
            }

            if (! $group->is_multiple && $inGroup->count() > 1) {
                $fail(__("$lang.option_single", ['name' => $item->name, 'group' => $group->name]));
            }

            foreach ($inGroup as $option) {
                $chosen[] = ['id' => $option->id, 'group' => $group->name, 'name' => $option->name, 'price' => $option->price];
            }
        }

        // Tuỳ chọn không thuộc món này.
        if (count($chosen) !== count($picked)) {
            $fail(__("$lang.item_unavailable"));
        }

        return $chosen;
    }
}
