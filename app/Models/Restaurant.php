<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use App\Enums\UserRole;
use App\Modules\ModuleManager;
use App\Tenancy\RestaurantLogo;
use App\Tenancy\RestaurantScope;
use App\Tenancy\RestaurantTheme;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'timezone', 'theme'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => RestaurantStatus::class, 'theme' => 'array'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(RestaurantDomain::class);
    }

    public function moduleStates(): HasMany
    {
        return $this->hasMany(RestaurantModule::class)->withoutGlobalScope(RestaurantScope::class);
    }

    public function owner(): ?User
    {
        return $this->users()->where('role', UserRole::Owner)->oldest('id')->first();
    }

    /** Domain riêng đang dùng: domain đã xác minh sớm nhất. */
    public function primaryDomain(): ?string
    {
        return $this->domains()
            ->whereNotNull('verified_at')
            ->orderBy('verified_at')
            ->orderBy('id')
            ->value('domain');
    }

    public function isLocked(): bool
    {
        return $this->status === RestaurantStatus::Locked;
    }

    public function logoUrl(): ?string
    {
        return RestaurantLogo::url($this);
    }

    public function themeCss(): string
    {
        return RestaurantTheme::css($this);
    }

    public function hasModule(string $module): bool
    {
        return app(ModuleManager::class)->isEnabled($this, $module);
    }
}
