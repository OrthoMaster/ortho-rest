<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Nội bộ: việc từng người làm ở bàn, từng khu theo buổi, lịch làm việc và ngày nghỉ.
 * Quán đông người: mặc định hiện 10 người làm nhiều nhất, lọc chọn người để xem; xuất Excel thì đủ cả.
 */
class Staff extends Report
{
    public const TOP = 10;

    /**
     * Lọc theo người đã chọn, hoặc lấy TOP người đầu (trừ khi xem tất cả).
     *
     * @param  Collection<int, array<string, mixed>>  $rows  có khoá 'id'
     */
    private function limitPeople(Collection $rows): Collection
    {
        $picked = array_map('intval', $this->picked('staff'));

        if ($picked !== []) {
            return $rows->filter(fn ($row) => in_array((int) $row['id'], $picked, true))->values();
        }

        return $this->showAll() ? $rows : $rows->take(self::TOP);
    }

    /** Ghi chú khi đang cắt bớt người. */
    private function cutNote(Collection $all, Collection $shown): ?string
    {
        return $shown->count() < $all->count() && $this->picked('staff') === []
            ? __('reports.top_people', ['count' => $shown->count(), 'total' => $all->count()]) : null;
    }

    public function blocks(): array
    {
        $blocks = [];
        $range = [$this->start(), $this->end()];

        if ($this->on('dinein')) {
            // Mỗi người: món đã gọi, đã bưng ra, đã làm xong (khu), đã huỷ; bàn đã đóng; bàn có sự cố mà mình phục vụ.
            $count = fn (string $column, string $time, array $where = []) => $this->table('dinein_lines')->whereNotNull($column)->whereBetween($time, $range)
                ->where($where)->selectRaw("$column as user_id, sum(quantity) as total")->groupBy($column)->pluck('total', 'user_id');
            $ordered = $count('ordered_by', 'created_at');
            $served = $count('served_by', 'served_at');
            $done = $count('done_by', 'done_at');
            $cancelled = $count('cancelled_by', 'cancelled_at', [['status', '=', 'cancelled']]);
            $closed = $this->table('dinein_tables')->whereNotNull('closed_by')->whereBetween('closed_at', $range)->selectRaw('closed_by as user_id, count(*) as total')->groupBy('closed_by')->pluck('total', 'user_id');
            $incidents = $this->table('dinein_incidents')->join('dinein_lines', 'dinein_lines.dinein_table_id', '=', 'dinein_incidents.dinein_table_id')
                ->whereNotNull('dinein_lines.served_by')->whereBetween('dinein_incidents.created_at', $range)
                ->selectRaw('dinein_lines.served_by as user_id, count(distinct dinein_incidents.dinein_table_id) as total')->groupBy('dinein_lines.served_by')->pluck('total', 'user_id');

            $ids = collect([$ordered, $served, $done, $cancelled, $closed, $incidents])->flatMap(fn (Collection $values) => $values->keys())->unique();
            $names = $this->restaurant->users()->whereIn('users.id', $ids)->pluck('name', 'users.id');
            $rows = $ids->map(fn ($id) => [
                'id' => $id,
                'name' => $names[$id] ?? '—',
                'served' => (int) ($served[$id] ?? 0),
                'cells' => [self::number($ordered[$id] ?? 0), self::number($served[$id] ?? 0), self::number($done[$id] ?? 0), self::number($closed[$id] ?? 0), self::number($cancelled[$id] ?? 0), self::number($incidents[$id] ?? 0)],
            ])->sortByDesc('served')->values();

            $shown = $this->limitPeople($rows);
            $blocks[] = ['type' => 'table', 'title' => __('reports.by_person'), 'wide' => true, 'numeric' => [1, 2, 3, 4, 5, 6], 'note' => __('reports.by_person_note'),
                'cut' => $this->cutNote($rows, $shown),
                'headers' => [__('reports.person'), __('reports.ordered'), __('reports.served'), __('reports.made'), __('reports.tables_closed'), __('reports.cancelled_qty'), __('reports.incident_tables')],
                'rows' => $shown->map(fn ($row) => [$row['name'], ...$row['cells']])->all(), 'empty' => __('reports.no_data')];

            // Khu bếp, bar theo buổi: số món làm xong và thời gian làm trung bình.
            $lines = $this->table('dinein_lines')->join('dinein_stations', 'dinein_stations.id', '=', 'dinein_lines.dinein_station_id')
                ->whereNotNull('dinein_lines.done_at')->whereBetween('dinein_lines.created_at', $range)->where('dinein_lines.status', '!=', 'cancelled')
                ->get(['dinein_stations.name as station', 'dinein_lines.quantity', 'dinein_lines.created_at', 'dinein_lines.done_at'])
                ->map(fn ($row) => (object) [
                    'station' => $row->station, 'quantity' => (int) $row->quantity, 'part' => self::dayPart(CarbonImmutable::parse($row->created_at)->hour),
                    'prep' => max(0, CarbonImmutable::parse($row->done_at)->getTimestamp() - CarbonImmutable::parse($row->created_at)->getTimestamp()),
                ]);
            $blocks[] = ['type' => 'table', 'title' => __('reports.station_parts'), 'numeric' => [2, 3],
                'headers' => [__('reports.station'), __('reports.part'), __('reports.items'), __('reports.prep_time')],
                'rows' => $lines->groupBy(fn ($line) => $line->station.'|'.$line->part)
                    ->sortBy(fn (Collection $rows, string $key) => $rows->first()->station.'|'.array_search($rows->first()->part, array_values(__('reports.parts')), true))
                    ->map(fn (Collection $rows, string $key) => [
                        ...explode('|', $key), self::number($rows->sum('quantity')), self::minutes($rows->avg('prep')),
                    ])->values()->all(), 'empty' => __('reports.no_data')];
        }

        if ($this->on('schedule')) {
            // Lịch làm việc: số ca, số giờ theo lịch (chưa có chấm công nên là giờ dự kiến), ngày nghỉ đã duyệt.
            $shifts = $this->table('shifts')->get(['id', 'start_time', 'end_time'])->keyBy('id')->map(function ($shift) {
                $seconds = strtotime($shift->end_time) - strtotime($shift->start_time);

                return $seconds <= 0 ? $seconds + 86400 : $seconds;
            });
            $assignments = $this->table('schedule_assignments')->whereBetween('work_date', [$this->from->toDateString(), $this->to->toDateString()])
                ->get(['user_id', 'work_date', 'shift_id'])->unique(fn ($row) => $row->user_id.'|'.$row->work_date.'|'.$row->shift_id);
            $leave = $this->table('leave_requests')->where('status', 'approved')
                ->whereBetween('starts_on', [$this->from->toDateString(), $this->to->toDateString()])
                ->selectRaw('user_id, sum(days) as days')->groupBy('user_id')->pluck('days', 'user_id');
            $ids = $assignments->pluck('user_id')->merge($leave->keys())->unique();
            $names = $this->restaurant->users()->whereIn('users.id', $ids)->pluck('name', 'users.id');

            $people = $ids->map(fn ($id) => [
                'id' => $id,
                'name' => $names[$id] ?? '—',
                'count' => $assignments->where('user_id', $id)->count(),
                'hours' => $assignments->where('user_id', $id)->sum(fn ($row) => $shifts[$row->shift_id] ?? 0) / 3600,
                'leave' => (float) ($leave[$id] ?? 0),
            ])->sortByDesc('hours')->values();
            $shownPeople = $this->limitPeople($people);

            $blocks[] = ['type' => 'table', 'title' => __('reports.schedule'), 'numeric' => [1, 2, 3], 'note' => __('reports.schedule_note'),
                'headers' => [__('reports.person'), __('reports.shifts'), __('reports.planned_hours'), __('reports.leave_days')],
                'cut' => $this->cutNote($people, $shownPeople),
                'rows' => $shownPeople->map(fn ($row) => [$row['name'], self::number($row['count']), number_format($row['hours'], 1, ',', '.'), number_format($row['leave'], 1, ',', '.')])
                    ->values()->all(), 'empty' => __('reports.no_data')];
        }

        return $blocks ?: [['type' => 'note', 'text' => __('reports.no_data'), 'wide' => true]];
    }
}
