<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Bán chạy: theo món, theo tuỳ chọn, theo giờ và thứ trong tuần (tại bàn và mang đi). */
class Sales extends Report
{
    /** @return Collection<int, object{name: string, quantity: int, amount: int, options: array, at: CarbonImmutable, source: string}> */
    public function items(): Collection
    {
        $items = collect();

        if ($this->on('dinein')) {
            $items = $items->merge($this->table('dinein_lines')->where('status', '!=', 'cancelled')->whereBetween('created_at', [$this->start(), $this->end()])
                ->get(['name', 'quantity', 'unit_price', 'options', 'created_at'])
                ->map(fn ($row) => (object) ['name' => $row->name, 'quantity' => (int) $row->quantity, 'amount' => (int) $row->unit_price * (int) $row->quantity,
                    'options' => json_decode((string) $row->options, true) ?: [], 'at' => CarbonImmutable::parse($row->created_at), 'source' => 'dinein']));
        }

        if ($this->on('takeaway')) {
            $items = $items->merge($this->table('takeaway_order_items')->join('takeaway_orders', 'takeaway_orders.id', '=', 'takeaway_order_items.takeaway_order_id')
                ->where('takeaway_orders.status', '!=', 'cancelled')->whereBetween('takeaway_orders.pickup_at', [$this->start(), $this->end()])
                ->get(['takeaway_order_items.name', 'takeaway_order_items.quantity', 'takeaway_order_items.unit_price', 'takeaway_order_items.options', 'takeaway_orders.pickup_at'])
                ->map(fn ($row) => (object) ['name' => $row->name, 'quantity' => (int) $row->quantity, 'amount' => (int) $row->unit_price * (int) $row->quantity,
                    'options' => json_decode((string) $row->options, true) ?: [], 'at' => CarbonImmutable::parse($row->pickup_at), 'source' => 'takeaway']));
        }

        return $items;
    }

    public function blocks(): array
    {
        $items = $this->items();

        if ($items->isEmpty()) {
            return [['type' => 'note', 'text' => __('reports.no_data'), 'wide' => true]];
        }

        $total = max(1, $items->sum('amount'));
        $top = $items->groupBy('name')->map(fn (Collection $rows, string $name) => [
            'name' => $name, 'quantity' => $rows->sum('quantity'), 'amount' => $rows->sum('amount'),
            'dinein' => $rows->where('source', 'dinein')->sum('quantity'), 'takeaway' => $rows->where('source', 'takeaway')->sum('quantity'),
        ])->sortByDesc('quantity')->values();
        $both = $this->on('dinein') && $this->on('takeaway');

        // Tuỳ chọn: đếm theo số phần đã bán có chọn tuỳ chọn đó (ví dụ Sườn cọng: 42 phần).
        $options = collect();
        foreach ($items as $item) {
            foreach ($item->options as $option) {
                if (is_array($option) && isset($option['name'])) {
                    $key = ($option['group'] ?? '').' · '.$option['name'];
                    $options[$key] = ($options[$key] ?? 0) + $item->quantity;
                }
            }
        }

        // Thứ trong tuần × giờ: số phần bán ra.
        $grid = [];
        foreach ($items as $item) {
            $grid[$item->at->dayOfWeekIso][$item->at->hour] = ($grid[$item->at->dayOfWeekIso][$item->at->hour] ?? 0) + $item->quantity;
        }
        $hours = self::hourSpan(collect(range(0, 23))->mapWithKeys(fn ($hour) => [$hour => collect($grid)->sum(fn ($day) => $day[$hour] ?? 0)])->all());

        return [
            ['type' => 'bars', 'title' => __('reports.top_items'), 'wide' => true,
                'items' => $top->take(15)->map(fn ($row) => ['label' => $row['name'], 'value' => $row['quantity'],
                    'text' => __('reports.portions', ['count' => self::number($row['quantity'])]).' · '.vnd($row['amount'])])->all()],
            ['type' => 'table', 'title' => __('reports.items_table'), 'wide' => true, 'numeric' => $both ? [1, 2, 3, 4, 5] : [1, 2, 3],
                'headers' => array_values(array_filter([__('reports.item'), __('reports.portions_label'), $both ? __('reports.source.dinein') : null, $both ? __('reports.source.takeaway') : null, __('reports.amount'), __('reports.share')])),
                'rows' => $top->map(fn ($row) => array_values(array_filter([$row['name'], self::number($row['quantity']),
                    $both ? self::number($row['dinein']) : null, $both ? self::number($row['takeaway']) : null, vnd($row['amount']), self::percent($row['amount'] / $total)], fn ($cell) => $cell !== null)))->all()],
            ['type' => 'bars', 'title' => __('reports.top_options'), 'note' => __('reports.top_options_note'),
                'items' => $options->sortDesc()->take(15)->map(fn ($count, $label) => ['label' => $label, 'value' => $count, 'text' => __('reports.portions', ['count' => self::number($count)])])->values()->all(),
                'empty' => __('reports.no_options')],
            ['type' => 'heatmap', 'title' => __('reports.heatmap'), 'note' => __('reports.heatmap_note'),
                'cols' => array_map(fn ($hour) => sprintf('%02dh', $hour), $hours),
                'rows' => collect(range(1, 7))->map(fn ($day) => ['label' => __('reports.weekdays.'.$day), 'cells' => array_map(fn ($hour) => $grid[$day][$hour] ?? 0, $hours)])->all()],
        ];
    }
}
