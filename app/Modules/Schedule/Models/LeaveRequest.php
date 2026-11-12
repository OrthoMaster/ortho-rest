<?php

namespace App\Modules\Schedule\Models;

use App\Models\Shift;
use App\Models\User;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Roster;
use App\Tenancy\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Đơn xin nghỉ: một khoảng ngày (nghỉ cả ngày) hoặc một số ca của một ngày. */
class LeaveRequest extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'shift_ids' => 'array',
            'days' => 'float',
            'status' => LeaveStatus::class,
            'is_paid' => 'boolean',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Đơn còn hiệu lực (chờ duyệt hoặc đã duyệt) chạm vào khoảng ngày này. */
    public function scopeActiveBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from);
    }

    public function isWholeDay(): bool
    {
        return $this->shift_ids === null;
    }

    /** Đơn có nghỉ vào ô này không (ngày + ca, hoặc cả ngày). */
    public function covers(CarbonImmutable $date, string $slotKey): bool
    {
        if ($date->lt($this->starts_on) || $date->gt($this->ends_on)) {
            return false;
        }

        return $this->isWholeDay() || $slotKey === Roster::WHOLE_DAY || in_array((int) $slotKey, $this->shift_ids, true);
    }

    /** "T3 10/11" hoặc "T3 10/11 – T5 12/11", kèm tên ca nếu nghỉ theo ca. */
    public function periodText(): string
    {
        return $this->isWholeDay() ? $this->dateText() : $this->dateText().' · '.$this->partText();
    }

    /** Chỉ phần ngày: "T3 10/11" hoặc "T3 10/11 – T5 12/11"; $long: "Thứ 3 10/11". */
    public function dateText(bool $long = false): string
    {
        $names = $long ? __('leave.weekdays') : __('ui.calendar.weekdays_short');
        $day = fn (CarbonImmutable $date) => $names[$date->dayOfWeekIso - 1].' '.$date->format('d/m');

        return $this->starts_on->equalTo($this->ends_on) ? $day($this->starts_on) : $day($this->starts_on).' – '.$day($this->ends_on);
    }

    /** "Cả ngày" hoặc tên các ca nghỉ. */
    public function partText(): string
    {
        return $this->isWholeDay() ? __('schedule.whole_day') : Shift::whereKey($this->shift_ids)->orderBy('start_time')->pluck('name')->implode(', ');
    }
}
