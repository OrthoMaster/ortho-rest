<?php

namespace App\Modules\Reservation\Models;

use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'department_ids' => 'array'];
    }

    /** Tổng chỗ ngồi của các khu vực đang dùng. */
    public static function totalSeats(): int
    {
        return (int) self::where('is_active', true)->sum('capacity');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function activityArea(): string
    {
        return 'settings';
    }

    /** Bàn số $number của khu này, ví dụ A12. */
    public function tableLabel(int $number): string
    {
        return $this->code.$number;
    }

    public function activityName(): string
    {
        return __('activity.names.area', ['name' => $this->name]);
    }

    public function activityFields(): array
    {
        return ['name' => 'text', 'code' => 'plain', 'table_count' => 'plain', 'capacity' => 'plain', 'is_active' => 'bool'];
    }
}
