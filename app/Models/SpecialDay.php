<?php

namespace App\Models;

use App\Enums\SpecialDayType;
use App\Support\Activity\RecordsActivity;
use App\Tenancy\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Một ngày khác lịch thường. Đóng cửa: periods rỗng là cả ngày, có thì là
 * các ca đóng. Mở cửa: periods là các ca mở của ngày đó, thay cho lịch thường.
 * Mỗi ca dạng ['name' => 'Trưa', 'start' => '10:00', 'end' => '14:00'].
 */
class SpecialDay extends Model
{
    use BelongsToRestaurant, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'type' => SpecialDayType::class,
            'periods' => 'array',
        ];
    }

    public static function key(string $name, string $start, string $end): string
    {
        return $name.'|'.$start.'|'.$end;
    }

    public function isWholeDay(): bool
    {
        return $this->type === SpecialDayType::Closed && $this->periods === [];
    }

    public function includesShift(Shift $shift): bool
    {
        return in_array(self::key($shift->name, $shift->start_time, $shift->end_time), $this->keys(), true);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(fn (array $period) => self::key($period['name'], $period['start'], $period['end']), $this->periods);
    }

    public function periodsText(): string
    {
        if ($this->isWholeDay()) {
            return __('special_days.whole_day');
        }

        return collect($this->periods)
            ->map(fn (array $period) => $period['name'].' '.$period['start'].' – '.$period['end'])
            ->join(', ');
    }

    /** Khoảng giờ nằm trọn trong một ca của ngày này. */
    public function covers(CarbonInterface $start, CarbonInterface $end): bool
    {
        foreach ($this->windows() as [$opens, $closes]) {
            if ($start->gte($opens) && $end->lte($closes)) {
                return true;
            }
        }

        return false;
    }

    /** Khoảng giờ chạm vào một ca của ngày này. */
    public function overlaps(CarbonInterface $start, CarbonInterface $end): bool
    {
        foreach ($this->windows() as [$opens, $closes]) {
            if ($start->lt($closes) && $end->gt($opens)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{CarbonImmutable, CarbonImmutable}> */
    private function windows(): array
    {
        return array_map(function (array $period): array {
            $opens = $this->date->setTimeFromTimeString($period['start']);
            $closes = $this->date->setTimeFromTimeString($period['end']);

            return [$opens, $closes->lte($opens) ? $closes->addDay() : $closes];
        }, $this->periods);
    }

    public function activityArea(): string
    {
        return 'settings';
    }

    public function activityName(): string
    {
        return __('activity.names.special_day', ['date' => $this->date->format('d/m/Y')]);
    }

    public function activityFields(): array
    {
        return ['type' => 'plain', 'periods' => 'long', 'reason' => 'text'];
    }
}
