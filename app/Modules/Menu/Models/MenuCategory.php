<?php

namespace App\Modules\Menu\Models;

use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MenuCategory extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
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
        return __('activity.names.category', ['name' => $this->name]);
    }

    public function activityFields(): array
    {
        return ['name' => 'text', 'description' => 'long', 'image_path' => 'image', 'is_visible' => 'bool'];
    }

    protected function activitySummary(string $action, array $changed): ?string
    {
        return $changed === ['is_visible']
            ? __($this->is_visible ? 'activity.shown' : 'activity.hidden', ['name' => $this->activityName()])
            : null;
    }
}
