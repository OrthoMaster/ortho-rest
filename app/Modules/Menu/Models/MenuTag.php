<?php

namespace App\Modules\Menu\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MenuTag extends Model
{
    use BelongsToRestaurant;

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
}
