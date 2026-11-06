<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use App\Enums\ShiftType;
use App\Enums\SpecialDayType;
use App\Enums\UserRole;
use App\Modules\ModuleManager;
use App\Support\Activity\RecordsActivity;
use App\Tenancy\RestaurantLogo;
use App\Tenancy\RestaurantScope;
use App\Tenancy\RestaurantTheme;
use Carbon\CarbonInterface;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'timezone', 'theme'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory, RecordsActivity;

    protected function casts(): array
    {
        return [
            'status' => RestaurantStatus::class,
            'theme' => 'array',
        ];
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

    /**
     * Khoảng giờ phải nằm trọn trong một ca mở cửa. Quán chưa khai ca nào thì
     * coi như mở cả ngày. Ngày đặc biệt được xét trước lịch thường.
     */
    public function isOpenBetween(CarbonInterface $start, CarbonInterface $end): bool
    {
        $special = SpecialDay::forRestaurant($this)->whereDate('date', $start->toDateString())->first();

        if ($special?->type === SpecialDayType::Open) {
            return $special->covers($start, $end);
        }

        if ($special && ($special->isWholeDay() || $special->overlaps($start, $end))) {
            return false;
        }

        $shifts = Shift::forRestaurant($this)->where('type', ShiftType::Opening)->get()
            ->reject(fn (Shift $shift): bool => $special !== null
                && $shift->weekday === $start->dayOfWeekIso
                && $special->includesShift($shift));

        return $shifts->isEmpty() || $shifts->contains(fn (Shift $shift): bool => $shift->covers($start, $end));
    }

    /**
     * Các ca mở cửa của một ngày, đã tính ngày đặc biệt.
     *
     * @return list<array{name: string, start: string, end: string}>
     */
    public function periodsOn(CarbonInterface $date): array
    {
        $special = SpecialDay::forRestaurant($this)->whereDate('date', $date->toDateString())->first();

        if ($special?->type === SpecialDayType::Open) {
            return $special->periods;
        }

        if ($special?->isWholeDay()) {
            return [];
        }

        return Shift::forRestaurant($this)
            ->where('type', ShiftType::Opening)
            ->where('weekday', $date->dayOfWeekIso)
            ->orderBy('start_time')
            ->get()
            ->reject(fn (Shift $shift): bool => $special !== null && $special->includesShift($shift))
            ->map(fn (Shift $shift): array => ['name' => $shift->name, 'start' => $shift->start_time, 'end' => $shift->end_time])
            ->values()
            ->all();
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

    public function activityRestaurantId(): int
    {
        return (int) $this->getKey();
    }

    public function activityArea(): string
    {
        return 'settings';
    }

    public function activityName(): string
    {
        return __('activity.names.restaurant');
    }

    public function activityFields(): array
    {
        return ['name' => 'text', 'slug' => 'plain', 'status' => 'plain', 'theme' => 'theme'];
    }

    protected function activityActions(): array
    {
        return ['updated'];
    }

    protected function activitySummary(string $action, array $changed): ?string
    {
        return $changed === ['status']
            ? __($this->isLocked() ? 'activity.restaurant_locked' : 'activity.restaurant_unlocked')
            : null;
    }
}
