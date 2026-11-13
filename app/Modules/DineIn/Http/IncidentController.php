<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Modules\DineIn\Models\Incident;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Danh sách sự cố khi phục vụ bàn: lọc theo ngày và loại, xuất Excel. */
class IncidentController extends Controller
{
    public const MAX_DAYS = 31;

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $kinds = filter_values('kind', Incident::KINDS);

        return view('modules.dinein.incidents', [
            'incidents' => $this->query($from, $to, $kinds)->paginate(50)->withQueryString(),
            'from' => $from,
            'to' => $to,
            'kinds' => $kinds,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->range($request);

        return SimpleXlsx::download('su-co-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx', [
            __('dinein.incident_at'), __('dinein.table_label'), __('dinein.incident_kind'), __('dinein.incident_note'),
            __('dinein.incident_by'), __('reservation.customer_name'), __('reservation.customer_phone'),
        ], $this->query($from, $to, filter_values('kind', Incident::KINDS))->get()->map(fn (Incident $incident) => [
            $incident->created_at->format('d/m/Y H:i'),
            $incident->diningTable->label,
            $incident->kindLabel(),
            $incident->note,
            $incident->creator?->name ?? '',
            $incident->diningTable->customer?->name ?? '',
            $incident->diningTable->customer?->phone ?? '',
        ]));
    }

    private function query(CarbonImmutable $from, CarbonImmutable $to, array $kinds): Builder
    {
        return Incident::with(['diningTable.customer', 'creator', 'offer.usedTable', 'offer.usedOrder'])
            ->whereBetween('created_at', [$from, $to->endOfDay()])
            ->when($kinds, fn (Builder $query) => $query->whereIn('kind', $kinds))
            ->latest('id');
    }

    /** @return array{CarbonImmutable, CarbonImmutable} mặc định 7 ngày gần nhất */
    private function range(Request $request): array
    {
        $parse = function (string $key) use ($request): ?CarbonImmutable {
            try {
                return $request->filled($key) ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query($key)) : null;
            } catch (Throwable) {
                return null;
            }
        };

        $to = $parse('to') ?? CarbonImmutable::today();
        $from = $parse('from') ?? $to->subDays(6);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return [$from, $to];
    }
}
