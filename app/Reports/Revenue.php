<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Doanh thu: một ngày thì theo giờ, một tuần hoặc một tháng thì theo ngày.
 * Bàn tính lúc đóng bàn (tạm tính trừ giảm giá), đơn mang đi tính theo giờ nhận (bỏ đơn huỷ).
 */
class Revenue extends Report
{
    public function period(): string
    {
        return $this->options['period'] ?? 'week';
    }

    protected function previous(): static
    {
        [$from, $to] = match ($this->period()) {
            'day' => [$this->from->subDay(), $this->to->subDay()],
            'month' => [$this->from->subMonthNoOverflow()->startOfMonth(), $this->from->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            default => [$this->from->subWeek(), $this->to->subWeek()],
        };

        return new static($this->restaurant, $from, $to, $this->options);
    }

    /** @return Collection<int, array{at: CarbonImmutable, source: string, total: int, discount: int, paid: bool, number: bool}> */
    public function bills(): Collection
    {
        $bills = collect();

        if ($this->on('dinein')) {
            $subtotal = DB::table('dinein_lines')->selectRaw('coalesce(sum(unit_price * quantity), 0)')
                ->whereColumn('dinein_lines.dinein_table_id', 'dinein_tables.id')->where('status', '!=', 'cancelled');

            $bills = $bills->merge($this->table('dinein_tables')->whereBetween('closed_at', [$this->start(), $this->end()])
                ->select('closed_at', 'discount_amount', 'paid_at', 'bill_number')->selectSub($subtotal, 'subtotal')->get()
                ->map(fn ($row) => [
                    'at' => CarbonImmutable::parse($row->closed_at), 'source' => 'dinein',
                    'total' => (int) $row->subtotal - (int) $row->discount_amount, 'discount' => (int) $row->discount_amount,
                    'paid' => $row->paid_at !== null, 'number' => $row->bill_number !== null,
                ]));
        }

        if ($this->on('takeaway')) {
            $bills = $bills->merge($this->table('takeaway_orders')->whereBetween('pickup_at', [$this->start(), $this->end()])
                ->where('status', '!=', 'cancelled')->get(['pickup_at', 'total', 'discount_amount', 'paid_at', 'bill_number'])
                ->map(fn ($row) => [
                    'at' => CarbonImmutable::parse($row->pickup_at), 'source' => 'takeaway',
                    'total' => (int) $row->total, 'discount' => (int) $row->discount_amount,
                    'paid' => $row->paid_at !== null, 'number' => $row->bill_number !== null,
                ]));
        }

        return $bills->values();
    }

    public function blocks(): array
    {
        $bills = $this->bills();
        $before = $this->previous()->bills();
        $total = $bills->sum('total');
        $count = $bills->count();
        $both = $this->on('dinein') && $this->on('takeaway');

        $kpis = [
            ['label' => __('reports.revenue_total'), 'value' => vnd($total), 'trend' => self::trend($total, $before->sum('total'))],
            ['label' => __('reports.bills'), 'value' => self::number($count), 'trend' => self::trend($count, $before->count())],
            ['label' => __('reports.bill_average'), 'value' => $count ? vnd(intdiv($total, $count)) : '—',
                'trend' => self::trend($count ? $total / $count : null, $before->count() ? $before->sum('total') / $before->count() : null)],
            ['label' => __('reports.discounts'), 'value' => vnd($bills->sum('discount'))],
        ];

        if ($this->on('cashier')) {
            $unpaid = $bills->where('paid', false);
            $kpis[] = ['label' => __('reports.unpaid'), 'value' => vnd($unpaid->sum('total')), 'note' => __('reports.bills_count', ['count' => $unpaid->count()]), 'warn' => $unpaid->isNotEmpty()];
            $missing = $bills->where('paid', true)->where('number', false)->count();
            $kpis[] = ['label' => __('reports.missing_numbers'), 'value' => self::number($missing), 'warn' => $missing > 0];
        }

        // Một ngày: theo giờ (bỏ giờ trống hai đầu); tuần, tháng: theo ngày.
        if ($this->period() === 'day') {
            $byHour = array_fill(0, 24, 0);
            foreach ($bills as $bill) {
                $byHour[$bill['at']->hour] += $bill['total'];
            }
            $buckets = collect(self::hourSpan($byHour))->mapWithKeys(fn (int $hour) => [$hour => sprintf('%02dh', $hour)]);
            $key = fn (array $bill) => $bill['at']->hour;
        } else {
            $buckets = collect($this->dayLabels());
            if ($this->period() === 'week') {
                $buckets = $buckets->map(fn (string $label, string $date) => __('reports.weekdays.'.CarbonImmutable::parse($date)->dayOfWeekIso).' '.$label);
            }
            $key = fn (array $bill) => $bill['at']->toDateString();
        }

        $grouped = $bills->groupBy($key);
        $items = $buckets->map(function (string $label, $bucket) use ($grouped) {
            $rows = $grouped->get($bucket, collect());

            return [
                'label' => $label,
                'value' => $rows->sum('total'),
                'text' => vnd($rows->sum('total')),
                'parts' => ['dinein' => $rows->where('source', 'dinein')->sum('total'), 'takeaway' => $rows->where('source', 'takeaway')->sum('total')],
                'count' => $rows->count(),
            ];
        })->values();

        $series = $both ? ['dinein' => __('reports.source.dinein'), 'takeaway' => __('reports.source.takeaway')] : null;

        return [
            ['type' => 'kpis', 'items' => $kpis, 'wide' => true],
            ['type' => 'columns', 'title' => __($this->period() === 'day' ? 'reports.revenue_by_hour' : 'reports.revenue_by_day'),
                'items' => $items->all(), 'series' => $series, 'wide' => true, 'money' => true],
            ['type' => 'table', 'title' => __('reports.revenue_table'), 'wide' => true, 'numeric' => $both ? [1, 2, 3, 4] : [1, 2],
                'headers' => array_values(array_filter([__($this->period() === 'day' ? 'reports.hour' : 'reports.day'), __('reports.bills'),
                    $both ? __('reports.source.dinein') : null, $both ? __('reports.source.takeaway') : null, __('reports.revenue_total')])),
                'rows' => $items->filter(fn ($item) => $item['count'] > 0)->map(fn ($item) => $both
                    ? [$item['label'], self::number($item['count']), vnd($item['parts']['dinein']), vnd($item['parts']['takeaway']), vnd($item['value'])]
                    : [$item['label'], self::number($item['count']), vnd($item['value'])])->values()->all(),
                'empty' => __('reports.no_data')],
        ];
    }
}
