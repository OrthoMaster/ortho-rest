<?php

namespace App\Models;

use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Chức vụ do quán tự đặt (Quản lý, Thu ngân, Phục vụ...), mỗi chức vụ một bộ quyền. */
class Position extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function activityArea(): string
    {
        return 'staff';
    }

    public function activityName(): string
    {
        return __('activity.names.position', ['name' => $this->name]);
    }

    public function activityFields(): array
    {
        return ['name' => 'text', 'permissions' => 'permissions'];
    }
}
