<?php

namespace App\Reports;

/** Trang đầu thống kê: con số chính so với kỳ trước và cảnh báo cần để ý. */
class Overview extends Report
{
    public function blocks(): array
    {
        $kpis = [];
        $alerts = [];
        $make = fn (string $class, ?self $base = null) => new $class($this->restaurant, ($base ?? $this)->from, ($base ?? $this)->to, $this->options);
        $before = $this->previous();

        if ($this->on('dinein') || $this->on('takeaway')) {
            $bills = $make(Revenue::class)->bills();
            $old = $make(Revenue::class, $before)->bills();
            $kpis[] = ['label' => __('reports.revenue_total'), 'value' => vnd($bills->sum('total')), 'trend' => self::trend($bills->sum('total'), $old->sum('total'))];
            $kpis[] = ['label' => __('reports.bills'), 'value' => self::number($bills->count()), 'trend' => self::trend($bills->count(), $old->count())];

            if ($this->on('cashier')) {
                $unpaid = $bills->where('paid', false);
                if ($unpaid->isNotEmpty()) {
                    $alerts[] = __('reports.alert_unpaid', ['count' => $unpaid->count(), 'total' => vnd($unpaid->sum('total'))]);
                }
                $missing = $bills->where('paid', true)->where('number', false)->count();
                if ($missing > 0) {
                    $alerts[] = __('reports.alert_missing', ['count' => $missing]);
                }
            }
        }

        if ($this->on('dinein')) {
            $service = $make(Service::class);
            $oldService = $make(Service::class, $before);
            $lines = $service->lines();
            $oldLines = $oldService->lines();
            $total = $lines->whereNotNull('total')->avg('total');
            $oldTotal = $oldLines->whereNotNull('total')->avg('total');
            $kpis[] = ['label' => __('reports.total_time'), 'value' => self::minutes($total), 'trend' => self::trend($total, $oldTotal, true)];
            $kpis[] = ['label' => __('reports.over_limit', ['minutes' => $this->limit()]), 'value' => self::percent($service->overLimit($lines)),
                'trend' => self::trend($service->overLimit($lines), $oldService->overLimit($oldLines), true)];

            if ($total && $oldTotal && $total > $oldTotal * 1.15) {
                $alerts[] = __('reports.alert_slower', ['percent' => self::percent(($total - $oldTotal) / $oldTotal)]);
            }

            $quality = $make(Quality::class);
            $incidents = $quality->incidents()->count();
            $oldIncidents = $make(Quality::class, $before)->incidents()->count();
            $kpis[] = ['label' => __('reports.incidents'), 'value' => self::number($incidents), 'trend' => self::trend($incidents, $oldIncidents, true)];
            $cancelled = $quality->cancelled();
            $kpis[] = ['label' => __('reports.cancelled_items'), 'value' => self::number($cancelled->sum('quantity')),
                'note' => __('reports.cancelled_after_done', ['count' => self::number($cancelled->whereNotNull('done_at')->sum('quantity'))])];

            if ($incidents > max(2, $oldIncidents * 1.5)) {
                $alerts[] = __('reports.alert_incidents', ['count' => $incidents, 'before' => $oldIncidents]);
            }
        }

        if ($this->on('reservation')) {
            $rate = Bookings::noShowRate($make(Bookings::class)->reservations());
            $kpis[] = ['label' => __('reports.no_show_rate'), 'value' => self::percent($rate),
                'trend' => self::trend($rate, Bookings::noShowRate($make(Bookings::class, $before)->reservations()), true)];
        }

        if ($this->on('takeaway')) {
            $late = Bookings::lateRate($make(Bookings::class)->orders());
            $kpis[] = ['label' => __('reports.takeaway_late_rate'), 'value' => self::percent($late),
                'trend' => self::trend($late, Bookings::lateRate($make(Bookings::class, $before)->orders()), true)];
        }

        return array_values(array_filter([
            $alerts ? ['type' => 'alerts', 'items' => $alerts, 'wide' => true] : null,
            ['type' => 'kpis', 'items' => $kpis, 'wide' => true, 'note' => __('reports.compare_note', ['from' => $before->from->format('d/m'), 'to' => $before->to->format('d/m')])],
        ]));
    }
}
