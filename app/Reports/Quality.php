<?php

namespace App\Reports;

use App\Models\CustomerOffer;
use App\Modules\DineIn\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Chất lượng phục vụ: sự cố, món huỷ (hao hụt), ưu đãi đã tặng và đã dùng. */
class Quality extends Report
{
    public function incidents(): Collection
    {
        return $this->table('dinein_incidents')
            ->join('dinein_tables', 'dinein_tables.id', '=', 'dinein_incidents.dinein_table_id')
            ->leftJoin('areas', 'areas.id', '=', 'dinein_tables.area_id')
            ->whereBetween('dinein_incidents.created_at', [$this->start(), $this->end()])
            ->get(['dinein_incidents.kind', 'dinein_incidents.created_at', 'areas.name as area'])
            ->map(fn ($row) => (object) ['kind' => $row->kind, 'at' => CarbonImmutable::parse($row->created_at), 'area' => $row->area]);
    }

    public function closedTables(): int
    {
        return $this->table('dinein_tables')->whereBetween('closed_at', [$this->start(), $this->end()])->count();
    }

    public function cancelled(): Collection
    {
        return $this->table('dinein_lines')->leftJoin('users', 'users.id', '=', 'dinein_lines.cancelled_by')
            ->where('dinein_lines.status', 'cancelled')->whereBetween('dinein_lines.cancelled_at', [$this->start(), $this->end()])
            ->get(['dinein_lines.name', 'dinein_lines.quantity', 'dinein_lines.unit_price', 'dinein_lines.done_at', 'users.name as by']);
    }

    public function blocks(): array
    {
        $incidents = $this->incidents();
        $tables = $this->closedTables();
        $before = $this->previous();
        $oldIncidents = $before->incidents()->count();
        $cancelled = $this->cancelled();
        $afterDone = $cancelled->whereNotNull('done_at');
        $lost = $afterDone->sum(fn ($row) => $row->unit_price * $row->quantity);

        // Ưu đãi tặng trong khoảng; ưu đãi đã dùng trong khoảng và số tiền đã giảm.
        $issued = $this->table('customer_offers')->whereBetween('created_at', [$this->start(), $this->end()])->get(['kind', 'used_at']);
        $usedDine = $this->table('dinein_tables')->whereNotNull('customer_offer_id')->whereBetween('closed_at', [$this->start(), $this->end()])->sum('discount_amount');
        $usedTakeaway = $this->on('takeaway')
            ? $this->table('takeaway_orders')->whereNotNull('customer_offer_id')->where('status', '!=', 'cancelled')->whereBetween('pickup_at', [$this->start(), $this->end()])->sum('discount_amount')
            : 0;
        $usedCount = $this->table('customer_offers')->whereBetween('used_at', [$this->start(), $this->end()])->count();

        $kpis = [
            ['label' => __('reports.incidents'), 'value' => self::number($incidents->count()), 'trend' => self::trend($incidents->count(), $oldIncidents, true)],
            ['label' => __('reports.incident_rate'), 'value' => $tables ? number_format($incidents->count() / $tables * 100, 1, ',', '.') : '—',
                'note' => __('reports.incident_rate_note'), 'trend' => self::trend(self::ratio($incidents->count(), $tables), self::ratio($oldIncidents, $before->closedTables()), true)],
            ['label' => __('reports.cancelled_items'), 'value' => self::number($cancelled->sum('quantity')),
                'note' => __('reports.cancelled_after_done', ['count' => self::number($afterDone->sum('quantity'))])],
            ['label' => __('reports.lost_value'), 'value' => vnd((int) $lost), 'note' => __('reports.lost_value_note'), 'warn' => $lost > 0],
            ['label' => __('reports.offers_issued'), 'value' => self::number($issued->count()), 'note' => __('reports.offers_used_note', ['count' => $usedCount])],
            ['label' => __('reports.offers_value'), 'value' => vnd((int) ($usedDine + $usedTakeaway)), 'note' => __('reports.offers_value_note')],
        ];

        $kinds = collect(Incident::KINDS)->map(fn (string $kind) => [
            'label' => __('dinein.incident_kinds.'.$kind),
            'value' => $incidents->where('kind', $kind)->count(),
            'text' => self::number($incidents->where('kind', $kind)->count()),
        ])->filter(fn ($item) => $item['value'] > 0)->sortByDesc('value')->values()->all();

        $byDay = $incidents->groupBy(fn ($row) => $row->at->toDateString());
        $dayItems = collect($this->dayLabels())->map(fn (string $label, string $date) => [
            'label' => $label, 'value' => $byDay->get($date)?->count() ?? 0, 'text' => self::number($byDay->get($date)?->count() ?? 0),
        ])->values()->all();

        $byHour = $incidents->groupBy(fn ($row) => $row->at->hour);
        $hourItems = collect(self::hourSpan($byHour->map->count()->all()))->map(fn (int $hour) => [
            'label' => sprintf('%02dh', $hour), 'value' => $byHour->get($hour)?->count() ?? 0, 'text' => self::number($byHour->get($hour)?->count() ?? 0),
        ])->values()->all();

        $areas = $incidents->groupBy(fn ($row) => $row->area ?? '—')
            ->map(fn (Collection $rows, string $area) => ['label' => $area, 'value' => $rows->count(), 'text' => self::number($rows->count())])
            ->sortByDesc('value')->values()->all();

        $items = $cancelled->groupBy('name')->map(fn (Collection $rows, string $name) => [
            'name' => $name,
            'quantity' => $rows->sum('quantity'),
            'after' => $rows->whereNotNull('done_at')->sum('quantity'),
            'lost' => $rows->whereNotNull('done_at')->sum(fn ($row) => $row->unit_price * $row->quantity),
        ])->sortByDesc('quantity')->take(15)->values();

        $people = $cancelled->groupBy(fn ($row) => $row->by ?? '—')->map(fn (Collection $rows, string $name) => [
            $name, self::number($rows->sum('quantity')), self::number($rows->whereNotNull('done_at')->sum('quantity')),
        ])->sortByDesc(fn ($row) => (int) str_replace('.', '', $row[1]))->values()->all();

        $offerKinds = collect([CustomerOffer::KIND_INCIDENT, CustomerOffer::KIND_LOYAL])->map(fn (string $kind) => [
            __('dinein.offer_kinds.'.$kind),
            self::number($issued->where('kind', $kind)->count()),
            self::number($issued->where('kind', $kind)->whereNotNull('used_at')->count()),
            self::percent(self::ratio($issued->where('kind', $kind)->whereNotNull('used_at')->count(), $issued->where('kind', $kind)->count())),
        ])->all();

        return [
            ['type' => 'kpis', 'items' => $kpis, 'wide' => true],
            ['type' => 'columns', 'title' => __('reports.incidents_by_day'), 'items' => $dayItems, 'wide' => true],
            ['type' => 'bars', 'title' => __('reports.incidents_by_kind'), 'items' => $kinds, 'empty' => __('reports.no_incidents')],
            ['type' => 'bars', 'title' => __('reports.incidents_by_area'), 'items' => $areas, 'empty' => __('reports.no_incidents')],
            ['type' => 'columns', 'title' => __('reports.incidents_by_hour'), 'items' => $hourItems, 'empty' => __('reports.no_incidents')],
            ['type' => 'table', 'title' => __('reports.offers_by_kind'), 'numeric' => [1, 2, 3], 'note' => __('reports.offers_by_kind_note'),
                'headers' => [__('reports.offer_kind'), __('reports.offers_issued'), __('reports.offers_used'), __('reports.offers_use_rate')], 'rows' => $offerKinds],
            ['type' => 'table', 'title' => __('reports.cancelled_by_item'), 'numeric' => [1, 2, 3], 'note' => __('reports.cancelled_note'),
                'headers' => [__('reports.item'), __('reports.cancelled_qty'), __('reports.cancelled_after'), __('reports.lost_value')],
                'rows' => $items->map(fn ($row) => [$row['name'], self::number($row['quantity']), self::number($row['after']), vnd((int) $row['lost'])])->all(),
                'empty' => __('reports.no_cancelled')],
            ['type' => 'table', 'title' => __('reports.cancelled_by_person'), 'numeric' => [1, 2],
                'headers' => [__('reports.person'), __('reports.cancelled_qty'), __('reports.cancelled_after')], 'rows' => $people, 'empty' => __('reports.no_cancelled')],
        ];
    }
}
