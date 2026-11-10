<?php

namespace App\Modules\Schedule\Models;

use App\Modules\Schedule\Roster;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** Lịch mặc định của một ô (thứ × ca × hàng): số người cần và người mặc định. */
class DefaultSlot extends Model
{
    use BelongsToRestaurant;

    protected $table = 'schedule_defaults';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'needed' => 'integer', 'user_ids' => 'array'];
    }

    public function slotKey(): string
    {
        return $this->shift_id ? (string) $this->shift_id : Roster::WHOLE_DAY;
    }

    public function laneKey(): string
    {
        return Roster::laneKey($this);
    }

    /** Số người cần: đặt riêng, không thì bằng số người mặc định. */
    public function required(): int
    {
        return $this->needed ?? count($this->user_ids ?? []);
    }
}
