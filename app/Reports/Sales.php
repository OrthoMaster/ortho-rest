<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Bán chạy: theo món và theo tuỳ chọn (tại bàn và mang đi).
 * Mặc định 5 món đầu; lọc chọn món, hoặc Xem tất cả; xuất Excel thì đủ cả.
 */
class Sales extends Report
{
    public const TOP = 5;

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

        // Tỷ trọng tính trên tổng tiền mọi món, kể cả khi đang lọc vài món.
        $total = max(1, $items->sum('amount'));

        // Lọc món đã chọn: biểu đồ, bảng và tuỳ chọn chỉ còn các món đó.
        if ($picked = $this->picked('items')) {
            $items = $items->filter(fn ($item) => in_array($item->name, $picked, true))->values();
        }

        if ($items->isEmpty()) {
            return [['type' => 'note', 'text' => __('reports.no_data'), 'wide' => true]];
        }

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

        $limit = $picked || $this->showAll() ? null : self::TOP;
        $shown = $limit ? $top->take($limit) : $top;
        $cut = $limit && $top->count() > $limit ? __('reports.top_items_cut', ['count' => $limit, 'total' => $top->count()]) : null;

        return [
            ['type' => 'bars', 'title' => __('reports.top_items'), 'wide' => true,
                'cut' => $cut,
                'items' => $shown->map(fn ($row) => ['label' => $row['name'], 'value' => $row['quantity'],
                    'text' => __('reports.portions', ['count' => self::number($row['quantity'])]).' · '.vnd($row['amount'])])->all()],
            ['type' => 'table', 'title' => __('reports.items_table'), 'wide' => true, 'numeric' => $both ? [1, 2, 3, 4, 5] : [1, 2, 3],
                'headers' => array_values(array_filter([__('reports.item'), __('reports.portions_label'), $both ? __('reports.source.dinein') : null, $both ? __('reports.source.takeaway') : null, __('reports.amount'), __('reports.share')])),
                'rows' => $shown->map(fn ($row) => array_values(array_filter([$row['name'], self::number($row['quantity']),
                    $both ? self::number($row['dinein']) : null, $both ? self::number($row['takeaway']) : null, vnd($row['amount']), self::percent($row['amount'] / $total)], fn ($cell) => $cell !== null)))->all()],
            ['type' => 'bars', 'title' => __('reports.top_options'), 'wide' => true, 'note' => __('reports.top_options_note'),
                'items' => $options->sortDesc()->take($limit ?? PHP_INT_MAX)->map(fn ($count, $label) => ['label' => $label, 'value' => $count, 'text' => __('reports.portions', ['count' => self::number($count)])])->values()->all(),
                'empty' => __('reports.no_options')],
        ];
    }
}
