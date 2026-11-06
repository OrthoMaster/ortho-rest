<?php

namespace App\Modules\Menu\Models;

use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MenuTag extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['image_only' => 'boolean'];
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
        return __('activity.names.tag', ['name' => $this->name]);
    }

    public function activityFields(): array
    {
        return ['name' => 'text', 'image_path' => 'image', 'image_only' => 'bool'];
    }
}
