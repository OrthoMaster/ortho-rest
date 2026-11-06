<?php

namespace App\Modules\Menu\Models;

use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use BelongsToRestaurant, RecordsActivity;

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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MenuTag::class, 'menu_item_tag')->orderBy('menu_tags.id');
    }

    /** @param list<int> $ids */
    public function syncTags(array $ids): void
    {
        $this->tags()->sync(array_fill_keys($ids, ['restaurant_id' => $this->restaurant_id]));
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

    public function activityArea(): string
    {
        return 'menu';
    }

    public function activityName(): string
    {
        return __('activity.names.item', ['name' => $this->name]);
    }

    public function activityFields(): array
    {
        return [
            'name' => 'text',
            'menu_category_id' => ['relation', MenuCategory::class],
            'description' => 'long',
            'image_path' => 'image',
            'price_dine_in' => 'money',
            'price_takeaway' => 'money',
            'is_sold_out' => 'bool',
            'is_visible' => 'bool',
        ];
    }

    protected function activitySummary(string $action, array $changed): ?string
    {
        return match ($changed) {
            ['is_sold_out'] => __($this->is_sold_out ? 'activity.sold_out' : 'activity.back_in_stock', ['name' => $this->activityName()]),
            ['is_visible'] => __($this->is_visible ? 'activity.shown' : 'activity.hidden', ['name' => $this->activityName()]),
            default => null,
        };
    }
}
