<?php

namespace App\Modules\Schedule\Models;

use App\Models\User;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần chỉnh phép còn lại bằng tay: từ bao nhiêu thành bao nhiêu, lý do, ai chỉnh. */
class LeaveAdjustment extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'days' => 'float',
            'before' => 'float',
            'after' => 'float',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
