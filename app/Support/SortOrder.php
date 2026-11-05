<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SortOrder
{
    public static function next(Builder $siblings): int
    {
        return (int) $siblings->max('sort_order') + 1;
    }

    /**
     * Xếp lại theo thứ tự mới. $ids có thể chỉ là một phần của nhóm (tab Đặt mang
     * đi không hiện món tại quán): các mục còn lại giữ nguyên vị trí.
     *
     * @param  list<int>  $ids
     */
    public static function reorder(Builder $siblings, array $ids): void
    {
        $model = $siblings->getModel();
        $current = (clone $siblings)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

        abort_if(array_diff($ids, $current) !== [] || count($ids) !== count(array_unique($ids)), 422);

        $queue = $ids;
        $moved = array_flip($ids);

        DB::transaction(function () use ($model, $current, $moved, &$queue): void {
            foreach ($current as $position => $id) {
                $model->newQuery()->whereKey(isset($moved[$id]) ? array_shift($queue) : $id)
                    ->update(['sort_order' => $position + 1]);
            }
        });
    }
}
