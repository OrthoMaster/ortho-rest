<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Tốc độ phục vụ tại bàn: thời gian ra món (gọi → khu làm xong → bưng ra), món chậm, món chờ quá ngưỡng,
 * món làm xong để lâu chưa bưng; thời gian khách ngồi bàn và vòng quay bàn.
 * Chỉ tính món đi qua khu (bếp, bar): món không qua khu mang ra luôn, không có thời gian làm.
 */
class Service extends Report
{
    /** Làm xong mà quá chừng này giây chưa bưng ra là chờ lâu. */
    public const SERVE_WAIT = 300;

    /** @return Collection<int, object{name: string, station: ?string, created: CarbonImmutable, prep: int, wait: ?int, total: ?int}> */
    public function lines(): Collection
    {
        return $this->table('dinein_lines')
            ->leftJoin('dinein_stations', 'dinein_stations.id', '=', 'dinein_lines.dinein_station_id')
            ->whereNotNull('dinein_lines.dinein_station_id')->whereNotNull('dinein_lines.done_at')
            ->where('dinein_lines.status', '!=', 'cancelled')
            ->whereBetween('dinein_lines.created_at', [$this->start(), $this->end()])
            ->get(['dinein_lines.name', 'dinein_stations.name as station', 'dinein_lines.created_at', 'dinein_lines.done_at', 'dinein_lines.served_at'])
            ->map(function ($row) {
                $created = CarbonImmutable::parse($row->created_at);
                $done = CarbonImmutable::parse($row->done_at);
                $served = $row->served_at ? CarbonImmutable::parse($row->served_at) : null;

                return (object) [
                    'name' => $row->name,
                    'station' => $row->station,
                    'created' => $created,
                    'prep' => max(0, $done->getTimestamp() - $created->getTimestamp()),
                    'wait' => $served ? max(0, $served->getTimestamp() - $done->getTimestamp()) : null,
                    'total' => $served ? max(0, $served->getTimestamp() - $created->getTimestamp()) : null,
                ];
            });
    }

    /** Lượt bàn đã đóng: khu, số bàn của khu, thời gian ngồi. */
    public function visits(): Collection
    {
        return $this->table('dinein_tables')->join('areas', 'areas.id', '=', 'dinein_tables.area_id')
            ->whereBetween('dinein_tables.closed_at', [$this->start(), $this->end()])
            ->get(['areas.id as area', 'areas.name', 'areas.table_count', 'dinein_tables.created_at', 'dinein_tables.closed_at'])
            ->map(fn ($row) => (object) [
                'area' => $row->area, 'name' => $row->name, 'tables' => (int) $row->table_count,
                'seconds' => max(0, CarbonImmutable::parse($row->closed_at)->getTimestamp() - CarbonImmutable::parse($row->created_at)->getTimestamp()),
            ]);
    }

    /** Tỷ lệ món quá ngưỡng (gọi → bưng ra). */
    public function overLimit(Collection $lines): ?float
    {
        $served = $lines->whereNotNull('total');

        return self::ratio($served->filter(fn ($line) => $line->total > $this->limit() * 60)->count(), $served->count());
    }

    public function blocks(): array
    {
        $lines = $this->lines();
        $before = $this->previous();
        $old = $before->lines();
        $visits = $this->visits();
        $limit = $this->limit();

        if ($lines->isEmpty() && $visits->isEmpty()) {
            return [['type' => 'note', 'text' => __('reports.no_data'), 'wide' => true]];
        }

        $avg = fn (Collection $rows, string $field) => $rows->whereNotNull($field)->avg($field);
        $kpis = [
            ['label' => __('reports.prep_time'), 'value' => self::minutes($avg($lines, 'prep')), 'note' => __('reports.prep_time_note'),
                'trend' => self::trend($avg($lines, 'prep'), $avg($old, 'prep'), true)],
            ['label' => __('reports.serve_wait'), 'value' => self::minutes($avg($lines, 'wait')), 'note' => __('reports.serve_wait_note'),
                'trend' => self::trend($avg($lines, 'wait'), $avg($old, 'wait'), true)],
            ['label' => __('reports.total_time'), 'value' => self::minutes($avg($lines, 'total')), 'note' => __('reports.total_time_note'),
                'trend' => self::trend($avg($lines, 'total'), $avg($old, 'total'), true)],
            ['label' => __('reports.over_limit', ['minutes' => $limit]), 'value' => self::percent($this->overLimit($lines)),
                'trend' => self::trend($this->overLimit($lines), $before->overLimit($old), true), 'warn' => ($this->overLimit($lines) ?? 0) > 0.2],
            ['label' => __('reports.sitting'), 'value' => self::minutes($visits->avg('seconds')), 'note' => __('reports.visits_count', ['count' => $visits->count()])],
        ];

        // Theo ngày và theo giờ trong ngày: thời gian gọi → bưng ra trung bình.
        $byDay = $lines->whereNotNull('total')->groupBy(fn ($line) => $line->created->toDateString());
        $dayItems = collect($this->dayLabels())->map(fn (string $label, string $date) => [
            'label' => $label,
            'value' => (float) ($byDay->get($date)?->avg('total') ?? 0) / 60,
            'text' => self::minutes($byDay->get($date)?->avg('total')),
        ])->values()->all();

        $byHour = $lines->whereNotNull('total')->groupBy(fn ($line) => $line->created->hour);
        $hourItems = collect(self::hourSpan($byHour->map->count()->all()))->map(fn (int $hour) => [
            'label' => sprintf('%02dh', $hour),
            'value' => (float) ($byHour->get($hour)?->avg('total') ?? 0) / 60,
            'text' => self::minutes($byHour->get($hour)?->avg('total')).' · '.__('reports.items_count', ['count' => $byHour->get($hour)?->count() ?? 0]),
        ])->values()->all();

        // Theo khu: làm, chờ bưng, quá ngưỡng, số món làm xong để quá 5 phút.
        $stations = $lines->groupBy('station')->map(fn (Collection $rows, string $station) => [
            $station,
            self::number($rows->count()),
            self::minutes($rows->avg('prep')),
            self::minutes($rows->whereNotNull('wait')->avg('wait')),
            self::percent($this->overLimit($rows)),
            self::number($rows->filter(fn ($line) => $line->wait !== null && $line->wait > self::SERVE_WAIT)->count()),
        ])->values()->all();

        // Món chậm: thời gian làm trung bình của món so với trung bình chung (ít nhất 3 lần gọi).
        $overall = $lines->avg('prep') ?: 1;
        $slow = $lines->groupBy('name')->filter(fn (Collection $rows) => $rows->count() >= 3)
            ->map(fn (Collection $rows, string $name) => ['name' => $name, 'count' => $rows->count(), 'avg' => $rows->avg('prep'), 'max' => $rows->max('prep')])
            ->sortByDesc('avg')->take(10)->values();

        // Làm xong để lâu chưa bưng ra: theo khu và buổi.
        $waiting = $lines->filter(fn ($line) => $line->wait !== null && $line->wait > self::SERVE_WAIT)
            ->groupBy(fn ($line) => $line->station.' · '.self::dayPart($line->created->hour))
            ->map(fn (Collection $rows, string $key) => ['label' => $key, 'value' => $rows->count(), 'text' => __('reports.items_count', ['count' => $rows->count()]).' · '.self::minutes($rows->avg('wait'))])
            ->sortByDesc('value')->values()->all();

        // Ngồi bàn, vòng quay: lượt mỗi bàn mỗi ngày = số lượt / (số bàn của khu × số ngày).
        $areas = $visits->groupBy('area')->map(fn (Collection $rows) => [
            $rows->first()->name,
            self::number($rows->count()),
            self::minutes($rows->avg('seconds')),
            $rows->first()->tables > 0 ? number_format($rows->count() / ($rows->first()->tables * $this->days()), 1, ',', '.') : '—',
        ])->values()->all();

        return [
            ['type' => 'kpis', 'items' => $kpis, 'wide' => true],
            ['type' => 'columns', 'title' => __('reports.total_time_by_day'), 'items' => $dayItems, 'unit' => __('reports.minutes_unit'), 'wide' => true],
            ['type' => 'columns', 'title' => __('reports.total_time_by_hour'), 'items' => $hourItems, 'unit' => __('reports.minutes_unit'), 'wide' => true],
            ['type' => 'table', 'title' => __('reports.by_station'), 'wide' => true, 'numeric' => [1, 2, 3, 4, 5],
                'headers' => [__('reports.station'), __('reports.items'), __('reports.prep_time'), __('reports.serve_wait'), __('reports.over_limit', ['minutes' => $limit]), __('reports.waited_long')],
                'rows' => $stations, 'empty' => __('reports.no_data')],
            ['type' => 'table', 'title' => __('reports.slow_items'), 'wide' => true, 'numeric' => [1, 2, 3, 4], 'note' => __('reports.slow_items_note'),
                'headers' => [__('reports.item'), __('reports.times_ordered'), __('reports.prep_time'), __('reports.longest'), __('reports.vs_average')],
                'rows' => $slow->map(fn ($row) => [$row['name'], self::number($row['count']), self::minutes($row['avg']), self::minutes($row['max']),
                    ($row['avg'] >= $overall ? '+' : '−').self::percent(abs($row['avg'] - $overall) / $overall)])->all(),
                'empty' => __('reports.not_enough')],
            ['type' => 'bars', 'title' => __('reports.waited_long_title'), 'note' => __('reports.waited_long_note'), 'items' => $waiting, 'empty' => __('reports.waited_long_none')],
            ['type' => 'table', 'title' => __('reports.turnover'), 'numeric' => [1, 2, 3], 'note' => __('reports.turnover_note'),
                'headers' => [__('reports.area'), __('reports.visits'), __('reports.sitting'), __('reports.turns_per_table')],
                'rows' => $areas, 'empty' => __('reports.no_data')],
        ];
    }
}
