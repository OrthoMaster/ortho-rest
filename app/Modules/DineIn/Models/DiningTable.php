<?php

namespace App\Modules\DineIn\Models;

use App\Models\User;
use App\Modules\DineIn\LineStatus;
use App\Modules\Reservation\Models\Area;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một lượt khách ở một bàn: mở khi gọi món đầu tiên, đóng khi khách về. */
class DiningTable extends Model
{
    use BelongsToRestaurant;

    protected $table = 'dinein_tables';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['table_number' => 'integer', 'closed_at' => 'immutable_datetime'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'dinein_table_id')->orderBy('round')->orderBy('id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** Tạm tính: các món chưa huỷ. */
    public function subtotal(): int
    {
        return $this->lines->filter(fn (OrderLine $line) => $line->status->billable())->sum(fn (OrderLine $line) => $line->total());
    }

    public function nextRound(): int
    {
        return (int) $this->lines()->max('round') + 1;
    }

    /** Còn món chưa mang ra thì chưa đóng bàn được. */
    public function hasPendingLines(): bool
    {
        return $this->lines()->whereIn('status', [LineStatus::New, LineStatus::Done])->exists();
    }
}
