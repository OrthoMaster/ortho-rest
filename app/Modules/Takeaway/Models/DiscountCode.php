<?php

namespace App\Modules\Takeaway\Models;

use App\Modules\Takeaway\DiscountType;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    use BelongsToRestaurant;

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
}
