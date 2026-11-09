<?php

namespace App\Modules\DineIn\Models;

use App\Modules\Menu\Models\MenuItem;
use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Khu phục vụ làm món: Bếp, Bar... Mỗi món thuộc một khu. */
class Station extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $table = 'dinein_stations';

    protected $guarded = ['id'];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'dinein_station_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'dinein_station_id');
    }

    /** Khu đầu tiên nhận các món chưa chọn khu. */
    public static function fallback(): ?self
    {
        return self::orderBy('sort_order')->orderBy('id')->first();
    }

    public function activityArea(): string
    {
        return 'dinein';
    }

    public function activityName(): string
    {
        return __('activity.names.station', ['name' => $this->name]);
    }

    public function activityFields(): array
    {
        return ['name' => 'text'];
    }
}
