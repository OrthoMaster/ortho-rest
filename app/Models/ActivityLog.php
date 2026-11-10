<?php

namespace App\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nhật ký thay đổi của quán: ai, lúc nào, đổi gì. Chỉ thêm, không sửa. */
class ActivityLog extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    public const AREAS = ['menu', 'reservation', 'takeaway', 'dinein', 'schedule', 'customers', 'staff', 'settings'];

    protected $guarded = ['id'];

    private static bool $recording = true;

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * Ghi một dòng nhật ký cho quán. Người làm lấy từ người đang đăng nhập.
     *
     * @param  list<string>  $changes
     */
    public static function record(int $restaurantId, string $area, string $summary, array $changes = [], ?Model $subject = null): void
    {
        if (! self::$recording) {
            return;
        }

        $request = app()->bound('request') ? request() : null;

        self::create([
            'restaurant_id' => $restaurantId,
            'user_id' => auth()->id(),
            'impersonator_id' => $request?->hasSession() ? $request->session()->get('impersonator_id') : null,
            'area' => $area,
            'summary' => mb_substr($summary, 0, 255),
            'changes' => array_values($changes),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);
    }

    /** Dữ liệu mẫu và việc của hệ thống không cần ghi. */
    public static function quietly(callable $callback): mixed
    {
        $previous = self::$recording;
        self::$recording = false;

        try {
            return $callback();
        } finally {
            self::$recording = $previous;
        }
    }

    public function actorName(): string
    {
        if ($this->user === null) {
            return in_array($this->area, ['reservation', 'takeaway'], true) ? __('activity.actor_customer') : __('activity.actor_system');
        }

        if ($this->impersonator) {
            return __('activity.actor_impersonated', ['name' => $this->user->name, 'admin' => $this->impersonator->name]);
        }

        return $this->user->is_super_admin ? __('activity.actor_liresto', ['name' => $this->user->name]) : $this->user->name;
    }
}
