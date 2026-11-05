<?php

namespace App\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class RestaurantModule extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'settings' => 'array',
        ];
    }
}
