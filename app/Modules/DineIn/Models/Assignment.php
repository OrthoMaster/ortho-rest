<?php

namespace App\Modules\DineIn\Models;

use App\Models\Shift;
use App\Models\User;
use App\Modules\Reservation\Models\Area;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một người làm ở một khu (bếp, bar) hoặc khu vực bàn trong một ca. */
class Assignment extends Model
{
    use BelongsToRestaurant;

    protected $table = 'dinein_assignments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['work_date' => 'immutable_date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'dinein_station_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
