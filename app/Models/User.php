<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Không dùng BelongsToRestaurant: user phải đọc được trước khi có tenant (đăng
 * nhập, session). Ranh giới giữa các quán do middleware tenant.member giữ, và
 * trong một quán thì luôn đi qua $restaurant->users().
 *
 * restaurant_id, role, is_super_admin, is_active, permissions cố ý không fillable.
 */
#[Fillable(['name', 'email', 'phone', 'password', 'locale'])]
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
            'is_active' => 'boolean',
            'permissions' => 'array',
            'started_on' => 'immutable_date',
            'paid_leave_per_month' => 'float',
            'contract_hours' => 'float',
        ];
    }

    /** Tài khoản mới mặc định đang làm việc (giống giá trị mặc định của cột). */
    protected $attributes = ['is_active' => true];

    /** @var list<string>|null quyền gộp từ các chức vụ, nhớ trong một request */
    private ?array $permissionKeysCache = null;

    /**
     * Khoá của mã đặt lại mật khẩu. Cùng một email có thể có tài khoản ở nhiều quán,
     * nên khoá kèm quán: mã xin ở quán này không dùng được ở quán khác, và không đè mã của nhau.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->restaurant_id ? $this->restaurant_id.'|'.$this->email : (string) $this->email;
    }

    /** Bộ phận theo hợp đồng (Bếp, Phục vụ...). */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class)->orderBy('sort_order')->orderBy('positions.id');
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    /**
     * Quyền gộp từ các chức vụ, cộng quyền riêng giao thẳng cho người này (ngoại lệ).
     *
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return $this->permissionKeysCache ??= array_values(array_unique([
            ...$this->positions()->withoutGlobalScopes()->pluck('permissions')->flatMap(fn ($keys) => is_array($keys) ? $keys : (json_decode($keys, true) ?? []))->all(),
            ...($this->permissions ?? []),
        ]));
    }

    public function refresh()
    {
        $this->permissionKeysCache = null;

        return parent::refresh();
    }

    public function forgetPermissions(): void
    {
        $this->permissionKeysCache = null;
        $this->unsetRelation('positions');
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
