<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SortOrder
{
    public static function next(Builder $siblings): int
    {
        return (int) $siblings->max('sort_order') + 1;
    }

    /** Đổi chỗ với mục liền trên (-1) hoặc liền dưới (1), rồi đánh số lại cả nhóm. */
    public static function move(Builder $siblings, Model $model, int $direction): void
    {
        $ids = $siblings->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $from = array_search($model->getKey(), $ids, true);
        $to = $from + $direction;

        if ($from === false || ! isset($ids[$to])) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

        foreach ($ids as $position => $id) {
            $model->newQuery()->whereKey($id)->update(['sort_order' => $position + 1]);
        }
    }
}
