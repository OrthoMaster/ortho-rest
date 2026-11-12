<?php

namespace App\Modules\DineIn\Models;

use App\Models\User;
use App\Modules\DineIn\LineStatus;
use App\Modules\Menu\HasChosenOptions;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một món đã gọi ở bàn. Giá đã gồm tiền cộng thêm của tuỳ chọn. */
class OrderLine extends Model
{
    use BelongsToRestaurant, HasChosenOptions;

    protected $table = 'dinein_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => LineStatus::class,
            'options' => 'array',
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'round' => 'integer',
            'done_at' => 'immutable_datetime',
            'served_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dinein_table_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'dinein_station_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    /** Món đi qua màn hình khu thì có trạng thái (Đang làm, Đã xong...); món không qua khu thì không hiện. */
    public function hasKitchenStatus(): bool
    {
        return $this->dinein_station_id !== null || $this->status === LineStatus::Cancelled;
    }

    /** Huỷ được khi chưa mang ra; món không qua khu (đã mang ra luôn) cũng huỷ được. */
    public function isCancellable(): bool
    {
        return in_array($this->status, [LineStatus::New, LineStatus::Done], true)
            || ($this->dinein_station_id === null && $this->status === LineStatus::Served);
    }

    public function total(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
