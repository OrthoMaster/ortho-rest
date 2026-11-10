<?php

namespace App\Modules\Schedule\Models;

use App\Modules\DineIn\Models\Station;
use App\Modules\Reservation\Models\Area;
use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Việc trong ca (Đứng thanh toán, Tiếp thực...), thuộc một khu hoặc chung cho cả quán. */
class Task extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $table = 'schedule_tasks';

    protected $guarded = ['id'];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'dinein_station_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** s12, a3, hoặc g (chung). */
    public function targetKey(): string
    {
        return match (true) {
            $this->dinein_station_id !== null => 's'.$this->dinein_station_id,
            $this->area_id !== null => 'a'.$this->area_id,
            default => 'g',
        };
    }

    public function activityArea(): string
    {
        return 'schedule';
    }

    public function activityName(): string
    {
        return __('activity.names.task', ['name' => $this->name]);
    }

    public function activityFields(): array
    {
        return ['name' => 'text'];
    }
}
