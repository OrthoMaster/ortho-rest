<?php

namespace App\Modules\Menu;

/** Món đã gọi có cột options: tuỳ chọn chép lúc gọi [{id, group, name, price}]. */
trait HasChosenOptions
{
    /**
     * Tuỳ chọn theo nhóm, mỗi nhóm một dòng: ["Thịt: Sườn cọng", "Nước chấm: Không ớt"].
     *
     * @return list<string>
     */
    public function optionLines(): array
    {
        return collect($this->options ?? [])
            ->groupBy(fn (array $option) => $option['group'] ?? '')
            ->map(fn ($options, string $group) => ($group !== '' ? $group.': ' : '').$options->pluck('name')->implode(', '))
            ->values()
            ->all();
    }

    /** Tuỳ chọn đã chọn, ví dụ "Sườn cọng, Không ớt". */
    public function optionText(): string
    {
        return collect($this->options ?? [])->pluck('name')->implode(', ');
    }

    /** @return list<int> id lựa chọn đã chọn, tăng dần (đơn cũ chưa lưu id thì bỏ qua). */
    public function optionIds(): array
    {
        $ids = array_map('intval', array_filter(array_column($this->options ?? [], 'id')));
        sort($ids);

        return $ids;
    }
}
