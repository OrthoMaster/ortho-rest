<?php

namespace App\Modules\Menu\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_dine_in' => 'integer',
            'price_takeaway' => 'integer',
            'is_sold_out' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /** Món khách đặt mang đi được lúc này. */
    public function scopeOrderableForTakeaway(Builder $query): Builder
    {
        return $query->whereNotNull('price_takeaway')
            ->where('is_visible', true)
            ->where('is_sold_out', false);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path
            ? Storage::disk(config('liresto.uploads_disk'))->url($this->image_path)
            : null;
    }
}
