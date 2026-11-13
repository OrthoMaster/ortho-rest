<?php

namespace App\Reports;

use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Một tab thống kê: nhận khoảng ngày, trả về các khối (con số, biểu đồ, bảng) để màn hình vẽ và xuất Excel.
 * Truy vấn thẳng bằng DB::table nên luôn lọc restaurant_id của quán đang xem.
 *
 * Khối: kpis, columns (cột theo thời gian), bars (xếp hạng), table, heatmap, note.
 */
abstract class Report
{
    public function __construct(
        protected Restaurant $restaurant,
        protected CarbonImmutable $from,
        protected CarbonImmutable $to,
        protected array $options = [],
    ) {}

    /** @return list<array<string, mixed>> */
    abstract public function blocks(): array;

    protected function table(string $name): Builder
    {
        return DB::table($name)->where($name.'.restaurant_id', $this->restaurant->id);
    }

    protected function start(): CarbonImmutable
    {
        return $this->from->startOfDay();
    }

    protected function end(): CarbonImmutable
    {
        return $this->to->endOfDay();
    }

    protected function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** Cùng độ dài, ngay trước khoảng đang xem (để so sánh). */
    protected function previous(): static
    {
        $days = $this->days();

        return new static($this->restaurant, $this->from->subDays($days), $this->to->subDays($days), $this->options);
    }

    protected function on(string $module): bool
    {
        return $this->restaurant->hasModule($module);
    }

    /** Ngưỡng món chờ lâu (phút), chọn ở bộ lọc. */
    protected function limit(): int
    {
        return (int) ($this->options['limit'] ?? 15);
    }

    /** 754 giây → "12,6 phút"; từ 60 phút → "1 giờ 5 phút". */
    public static function minutes(?float $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $minutes = $seconds / 60;

        if ($minutes >= 60) {
            return __('reports.hours_minutes', ['hours' => intdiv((int) round($minutes), 60), 'minutes' => (int) round($minutes) % 60]);
        }

        return __('reports.minutes', ['count' => number_format($minutes, 1, ',', '.')]);
    }

    public static function percent(?float $value): string
    {
        return $value === null ? '—' : number_format($value * 100, $value * 100 < 10 ? 1 : 0, ',', '.').'%';
    }

    public static function number(float|int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    protected static function ratio(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? $part / $whole : null;
    }

    /**
     * So với kỳ trước. $lowerIsBetter: thời gian, sự cố… giảm là tốt.
     *
     * @return array{text: string, good: bool}|null
     */
    protected static function trend(?float $now, ?float $before, bool $lowerIsBetter = false): ?array
    {
        if ($now === null || $before === null || $before == 0) {
            return null;
        }

        $change = ($now - $before) / abs($before);

        if (abs($change) < 0.005) {
            return ['text' => __('reports.trend_same'), 'good' => true];
        }

        return [
            'text' => __($change > 0 ? 'reports.trend_up' : 'reports.trend_down', ['percent' => self::percent(abs($change))]),
            'good' => $lowerIsBetter ? $change < 0 : $change > 0,
        ];
    }

    /** Buổi trong ngày theo giờ. */
    protected static function dayPart(int $hour): string
    {
        return match (true) {
            $hour < 11 => __('reports.parts.morning'),
            $hour < 14 => __('reports.parts.noon'),
            $hour < 17 => __('reports.parts.afternoon'),
            default => __('reports.parts.evening'),
        };
    }

    /** Mỗi ngày trong khoảng một cột (biểu đồ theo ngày). @return array<string, string> [Y-m-d => "13/11"] */
    protected function dayLabels(): array
    {
        $labels = [];

        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $labels[$day->toDateString()] = $day->format('d/m');
        }

        return $labels;
    }

    /** Khoảng giờ có dữ liệu (bỏ giờ trống hai đầu). @param  array<int, mixed>  $byHour  @return list<int> */
    protected static function hourSpan(array $byHour): array
    {
        $hours = array_keys(array_filter($byHour, fn ($value) => $value > 0));

        return $hours === [] ? [] : range(min($hours), max($hours));
    }
}
