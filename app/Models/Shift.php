<?php

namespace App\Models;

use App\Enums\ShiftType;
use App\Tenancy\BelongsToRestaurant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ShiftType::class,
            'weekday' => 'integer',
        ];
    }

    /** Giờ kết thúc không sau giờ bắt đầu nghĩa là ca kéo qua nửa đêm. */
    public function crossesMidnight(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    public function timeRange(): string
    {
        $range = $this->start_time.' – '.$this->end_time;

        return $this->crossesMidnight() ? $range.' '.__('shifts.next_day') : $range;
    }

    /** Khoảng thời gian có nằm trọn trong ca không. Xét cả ca từ hôm trước kéo qua. */
    public function covers(CarbonInterface $start, CarbonInterface $end): bool
    {
        foreach ([$start->toImmutable()->startOfDay(), $start->toImmutable()->subDay()->startOfDay()] as $day) {
            if ($day->dayOfWeekIso !== $this->weekday) {
                continue;
            }

            $opens = $day->setTimeFromTimeString($this->start_time);
            $closes = $day->setTimeFromTimeString($this->end_time)->addDays($this->crossesMidnight() ? 1 : 0);

            if ($start->gte($opens) && $end->lte($closes)) {
                return true;
            }
        }

        return false;
    }

    public function overlaps(string $start, string $end): bool
    {
        $minutes = function (string $from, string $to): array {
            $begin = $this->toMinutes($from);
            $finish = $this->toMinutes($to);

            return [$begin, $finish <= $begin ? $finish + 1440 : $finish];
        };

        [$mineStart, $mineEnd] = $minutes($this->start_time, $this->end_time);
        [$otherStart, $otherEnd] = $minutes($start, $end);

        return $mineStart < $otherEnd && $otherStart < $mineEnd;
    }

    private function toMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}
