<?php

namespace App\Modules\Reservation\Models;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReservationStatusLog::class)->orderBy('id');
    }

    public function activityArea(): string
    {
        return 'reservation';
    }

    public function activityName(): string
    {
        return __('activity.names.reservation', [
            'customer' => $this->customer?->name,
            'time' => $this->reserved_at->format('H:i d/m'),
        ]);
    }

    public function activityFields(): array
    {
        return [
            'status' => 'plain',
            'reserved_at' => 'plain',
            'party_size' => 'plain',
            'area_id' => ['relation', Area::class],
            'notes' => 'long',
        ];
    }
}
