<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Không dùng BelongsToRestaurant: user phải đọc được trước khi có tenant (đăng
 * nhập, session). Ranh giới giữa các quán do middleware tenant.member giữ, và
 * trong một quán thì luôn đi qua $restaurant->users().
 *
 * restaurant_id, role, is_super_admin cố ý không fillable.
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_super_admin' => 'boolean',
            'devices_locked' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** Super admin đăng nhập ở trang Liresto, người của quán ở trang riêng của quán. */
    public function loginUrl(): string
    {
        return $this->is_super_admin || $this->restaurant === null
            ? route('login')
            : route('tenant.login', ['resto' => $this->restaurant->slug]);
    }

    public function homeUrl(): string
    {
        if ($this->is_super_admin) {
            return route('super.restaurants.index');
        }

        return route('tenant.admin.dashboard', ['resto' => $this->restaurant->slug]);
    }
}
