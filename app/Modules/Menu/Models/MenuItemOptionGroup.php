<?php

namespace App\Modules\Menu\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một nhóm tuỳ chọn của món, ví dụ "Nước chấm": Không ớt, Nhiều ớt. */
class MenuItemOptionGroup extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_multiple' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(MenuItemOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Một dòng tóm tắt cho nhật ký: Thịt (Sườn cọng +10.000 ₫, Trứng). */
    public function summary(): string
    {
        return $this->name.' ('.$this->options->map(fn (MenuItemOption $option) => $option->name.($option->price > 0 ? ' +'.vnd($option->price) : ''))->implode(', ').')';
    }
}
