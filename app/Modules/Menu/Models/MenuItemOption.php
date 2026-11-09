<?php

namespace App\Modules\Menu\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemOption extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuItemOptionGroup::class, 'menu_item_option_group_id');
    }
}
