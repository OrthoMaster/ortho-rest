<?php

namespace App\Modules\Takeaway\Models;

use App\Modules\Takeaway\DiscountType;
use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function usable(?string $code): ?self
    {
        return static::where('code', strtoupper(trim((string) $code)))->where('is_active', true)->first();
    }

    public function label(): string
    {
        return $this->type->label($this->value);
    }

    public function activityArea(): string
    {
        return 'takeaway';
    }

    public function activityName(): string
    {
        return __('activity.names.discount', ['code' => $this->code]);
    }

    public function activityFields(): array
    {
        return ['code' => 'text', 'type' => 'plain', 'value' => 'plain', 'is_active' => 'bool'];
    }
}
