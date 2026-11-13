<?php

namespace App\Reports;

use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Đặt bàn trước (khách không đến, huỷ, đặt trước bao lâu, kênh, giờ đông) và đơn mang đi trễ hẹn. */
class Bookings extends Report
{
    public function reservations(): Collection
    {
        if (! $this->on('reservation')) {
            return collect();
        }

        return $this->table('reservations')->whereBetween('reserved_at', [$this->start(), $this->end()])
            ->get(['status', 'source', 'party_size', 'reserved_at', 'created_at'])
            ->map(fn ($row) => (object) [
                'status' => ReservationStatus::from($row->status), 'source' => $row->source, 'party' => (int) $row->party_size,
                'at' => CarbonImmutable::parse($row->reserved_at),
                'lead' => max(0, CarbonImmutable::parse($row->reserved_at)->getTimestamp() - CarbonImmutable::parse($row->created_at)->getTimestamp()),
            ]);
    }

    /** Khách không đến / (đã đến + không đến): chỉ tính đặt bàn đã tới giờ. */
    public static function noShowRate(Collection $reservations): ?float
    {
        $noShow = $reservations->where('status', ReservationStatus::NoShow)->count();
        $came = $reservations->whereIn('status', [ReservationStatus::Seated, ReservationStatus::Completed])->count();

        return self::ratio($noShow, $noShow + $came);
    }

    /** Đơn mang đi đã làm xong (có giờ làm xong): trễ là làm xong sau giờ hẹn. */
    public function orders(): Collection
    {
        if (! $this->on('takeaway')) {
            return collect();
        }

        return $this->table('takeaway_orders')->whereBetween('pickup_at', [$this->start(), $this->end()])->whereNotNull('ready_at')
            ->where('status', '!=', 'cancelled')->get(['pickup_at', 'ready_at', 'delivered_at'])
            ->map(fn ($row) => (object) [
                'late' => CarbonImmutable::parse($row->ready_at)->getTimestamp() - CarbonImmutable::parse($row->pickup_at)->getTimestamp(),
                'handover' => $row->delivered_at ? CarbonImmutable::parse($row->delivered_at)->getTimestamp() - CarbonImmutable::parse($row->pickup_at)->getTimestamp() : null,
                'hour' => CarbonImmutable::parse($row->pickup_at)->hour,
            ]);
    }

    public static function lateRate(Collection $orders): ?float
    {
        return self::ratio($orders->filter(fn ($order) => $order->late > 0)->count(), $orders->count());
    }

    public function blocks(): array
    {
        $blocks = [];
        $reservations = $this->reservations();
        $orders = $this->orders();
        $before = $this->previous();

        if ($this->on('reservation')) {
            $old = $before->reservations();
            $cancelled = fn (Collection $rows) => self::ratio($rows->whereIn('status', [ReservationStatus::Cancelled, ReservationStatus::Rejected])->count(), $rows->count());
            $blocks[] = ['type' => 'kpis', 'wide' => true, 'items' => [
                ['label' => __('reports.reservations'), 'value' => self::number($reservations->count()), 'note' => __('reports.guests_count', ['count' => self::number($reservations->sum('party'))]),
                    'trend' => self::trend($reservations->count(), $old->count())],
                ['label' => __('reports.no_show_rate'), 'value' => self::percent(self::noShowRate($reservations)), 'note' => __('reports.no_show_note'),
                    'trend' => self::trend(self::noShowRate($reservations), self::noShowRate($old), true), 'warn' => (self::noShowRate($reservations) ?? 0) > 0.1],
                ['label' => __('reports.cancel_rate'), 'value' => self::percent($cancelled($reservations)), 'trend' => self::trend($cancelled($reservations), $cancelled($old), true)],
                ['label' => __('reports.party_average'), 'value' => $reservations->isEmpty() ? '—' : number_format($reservations->avg('party'), 1, ',', '.')],
            ]];

            $statuses = collect(ReservationStatus::cases())->map(fn (ReservationStatus $status) => [
                'label' => $status->label(), 'value' => $reservations->where('status', $status)->count(), 'text' => self::number($reservations->where('status', $status)->count()),
            ])->filter(fn ($item) => $item['value'] > 0)->values()->all();

            $sources = collect(ReservationSource::cases())->map(fn (ReservationSource $source) => [
                'label' => $source->label(), 'value' => $reservations->where('source', $source->value)->count(),
                'text' => self::number($reservations->where('source', $source->value)->count()).' · '.self::percent(self::ratio($reservations->where('source', $source->value)->count(), $reservations->count())),
            ])->filter(fn ($item) => $item['value'] > 0)->values()->all();

            // Đặt trước bao lâu.
            $leads = [
                __('reports.lead.hours') => fn ($lead) => $lead < 3 * 3600,
                __('reports.lead.same_day') => fn ($lead) => $lead >= 3 * 3600 && $lead < 24 * 3600,
                __('reports.lead.days') => fn ($lead) => $lead >= 24 * 3600 && $lead < 3 * 86400,
                __('reports.lead.week') => fn ($lead) => $lead >= 3 * 86400 && $lead < 8 * 86400,
                __('reports.lead.more') => fn ($lead) => $lead >= 8 * 86400,
            ];
            $leadItems = collect($leads)->map(fn ($test, $label) => [
                'label' => $label, 'value' => $reservations->filter(fn ($row) => $test($row->lead))->count(),
                'text' => self::number($reservations->filter(fn ($row) => $test($row->lead))->count()),
            ])->values()->all();

            $byHour = $reservations->groupBy(fn ($row) => $row->at->hour);
            $hourItems = collect(self::hourSpan($byHour->map->count()->all()))->map(fn (int $hour) => [
                'label' => sprintf('%02dh', $hour), 'value' => $byHour->get($hour)?->count() ?? 0,
                'text' => __('reports.reservations_guests', ['count' => $byHour->get($hour)?->count() ?? 0, 'guests' => $byHour->get($hour)?->sum('party') ?? 0]),
            ])->values()->all();

            $blocks[] = ['type' => 'columns', 'title' => __('reports.reservations_by_hour'), 'items' => $hourItems, 'wide' => true, 'empty' => __('reports.no_data')];
            $blocks[] = ['type' => 'bars', 'title' => __('reports.reservations_by_status'), 'items' => $statuses, 'empty' => __('reports.no_data')];
            $blocks[] = ['type' => 'bars', 'title' => __('reports.reservations_by_source'), 'items' => $sources, 'empty' => __('reports.no_data')];
            $blocks[] = ['type' => 'bars', 'title' => __('reports.reservations_lead'), 'items' => $leadItems, 'empty' => __('reports.no_data')];
        }

        if ($this->on('takeaway')) {
            $late = $orders->filter(fn ($order) => $order->late > 0);
            $handover = $orders->whereNotNull('handover')->filter(fn ($order) => $order->handover > 0);
            $blocks[] = ['type' => 'kpis', 'wide' => true, 'title' => __('reports.takeaway_title'), 'items' => [
                ['label' => __('reports.takeaway_done'), 'value' => self::number($orders->count())],
                ['label' => __('reports.takeaway_late_rate'), 'value' => self::percent(self::lateRate($orders)), 'note' => __('reports.takeaway_late_note'),
                    'trend' => self::trend(self::lateRate($orders), self::lateRate($before->orders()), true), 'warn' => (self::lateRate($orders) ?? 0) > 0.1],
                ['label' => __('reports.takeaway_late_avg'), 'value' => self::minutes($late->avg('late'))],
                ['label' => __('reports.takeaway_handover'), 'value' => self::number($handover->count()), 'note' => __('reports.takeaway_handover_note')],
            ]];

            $byHour = $orders->groupBy('hour');
            $blocks[] = ['type' => 'columns', 'title' => __('reports.takeaway_late_by_hour'), 'wide' => true, 'empty' => __('reports.takeaway_no_data'),
                'items' => collect(self::hourSpan($byHour->map->count()->all()))->map(fn (int $hour) => [
                    'label' => sprintf('%02dh', $hour),
                    'value' => $byHour->get($hour)?->filter(fn ($order) => $order->late > 0)->count() ?? 0,
                    'text' => __('reports.late_of', ['late' => $byHour->get($hour)?->filter(fn ($order) => $order->late > 0)->count() ?? 0, 'total' => $byHour->get($hour)?->count() ?? 0]),
                ])->values()->all()];
        }

        return $blocks;
    }
}
