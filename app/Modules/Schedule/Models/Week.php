<?php

namespace App\Modules\Schedule\Models;

use App\Models\User;
use App\Tenancy\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trạng thái lịch của một tuần (thứ 2 đến chủ nhật). Chưa công bố thì là bản nháp:
 * chỉ người phân công thấy, nhân viên chưa thấy và chưa được tính là đang làm.
 */
class Week extends Model
{
    use BelongsToRestaurant;

    protected $table = 'schedule_weeks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'week_start' => 'immutable_date',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** Tuần chứa ngày này (chưa có thì là bản nháp mới, chưa lưu). */
    public static function of(CarbonImmutable $date): self
    {
        $monday = $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString();

        return self::whereDate('week_start', $monday)->first() ?? new self(['week_start' => $monday]);
    }

    public static function isPublishedOn(CarbonImmutable $date): bool
    {
        return self::of($date)->isPublished();
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Xoá hết lịch được khi tuần còn là nháp, hoặc đã công bố nhưng chưa bắt đầu. */
    public function canReset(): bool
    {
        return ! $this->isPublished() || $this->week_start->isAfter(CarbonImmutable::today());
    }
}
