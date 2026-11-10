<?php

namespace App\Models;

use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bộ phận theo hợp đồng (Bếp, Phục vụ, Bar...), mỗi bộ phận một màu trên lịch. */
class Department extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    /** Số màu có trong app.css (--tone-1 ... --tone-8). */
    public const TONES = 8;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tone' => 'integer'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Màu tiếp theo chưa dùng, hết thì quay vòng. */
    public static function nextTone(): int
    {
        $used = self::pluck('tone')->all();

        foreach (range(1, self::TONES) as $tone) {
            if (! in_array($tone, $used, true)) {
                return $tone;
            }
        }

        return count($used) % self::TONES + 1;
    }

    public function activityArea(): string
    {
        return 'staff';
    }

    public function activityName(): string
    {
        return __('activity.names.department', ['name' => $this->name]);
    }

    public function activityFields(): array
    {
        return ['name' => 'text'];
    }
}
