<?php

namespace App\Modules\Website\Models;

use App\Models\User;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trang đặt bàn quán tự tạo (thường nhờ AI làm): một file HTML. Bản nháp phải
 * được xem thử và gửi thử thành công mới đăng được. Đăng bản mới thì bản cũ
 * thành bản lưu, khôi phục lại được.
 */
class BookingPage extends Model
{
    use BelongsToRestaurant;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const ARCHIVED = 'archived';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'checks' => 'array',
            'tested_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasErrors(): bool
    {
        return collect($this->checks)->contains('level', 'error');
    }

    public function canPublish(): bool
    {
        return $this->status === self::DRAFT && ! $this->hasErrors() && $this->tested_at !== null;
    }

    public static function draft(): ?self
    {
        return self::where('status', self::DRAFT)->latest('id')->first();
    }

    public static function published(): ?self
    {
        return self::where('status', self::PUBLISHED)->latest('id')->first();
    }
}
