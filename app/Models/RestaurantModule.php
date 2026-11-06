<?php

namespace App\Models;

use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class RestaurantModule extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function activityArea(): string
    {
        return 'settings';
    }

    public function activityName(): string
    {
        return __('activity.names.module', ['name' => __(config('modules.'.$this->module.'.name'))]);
    }

    public function activityFields(): array
    {
        return ['enabled' => 'bool', 'settings' => 'settings'];
    }

    protected function activityActions(): array
    {
        return ['created', 'updated'];
    }

    protected function activitySummary(string $action, array $changed): ?string
    {
        if ($action === 'created' || $changed === ['enabled']) {
            return __($this->enabled ? 'activity.module_enabled' : 'activity.module_disabled', ['name' => $this->activityName()]);
        }

        return null;
    }
}
