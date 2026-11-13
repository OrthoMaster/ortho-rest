<?php

namespace App\Modules\Cashier\Http;

use App\Http\Controllers\Controller;
use App\Modules\Cashier\Bill;
use App\Modules\Cashier\Cashier;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Đối soát: mọi phiếu (bàn, đơn mang đi) trong khoảng ngày, đã thu hay chưa, số hoá đơn máy tính tiền.
 * Cuối ca còn phiếu chưa thu là khách đi chưa trả hoặc quầy quên bấm.
 */
class ReconcileController extends Controller
{
    public const MAX_DAYS = 31;

    public const FILTERS = [
        'paid' => ['paid', 'unpaid'],
        'number' => ['has', 'missing'],
        'source' => ['dinein', 'takeaway'],
    ];

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $filters = $this->filters();
        $search = trim((string) $request->query('q'));
        $bills = $this->bills($from, $to, $search, $filters);
        $paid = $bills->filter(fn (Bill $bill) => $bill->paidAt() !== null);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('modules.cashier.reconcile', [
            'bills' => (new LengthAwarePaginator($bills->forPage($page, 50)->values(), $bills->count(), 50, $page, ['path' => $request->url()]))->withQueryString(),
            'totals' => [
                'count' => $bills->count(),
                'paid' => $paid->sum(fn (Bill $bill) => $bill->total()),
                'unpaid_count' => $bills->count() - $paid->count(),
                'unpaid' => $bills->sum(fn (Bill $bill) => $bill->total()) - $paid->sum(fn (Bill $bill) => $bill->total()),
                'missing' => $paid->filter(fn (Bill $bill) => $bill->billNumber() === null)->count(),
            ],
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->range($request);
        $bills = $this->bills($from, $to, trim((string) $request->query('q')), $this->filters());

        return SimpleXlsx::download('doi-soat-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx', [
            __('cashier.col_at'), __('cashier.col_bill'), __('dinein.bill_number'), __('dinein.bill_subtotal'), __('dinein.bill_discount'),
            __('dinein.bill_total'), __('cashier.col_paid_at'), __('cashier.col_paid_by'), __('reservation.customer_name'),
        ], $bills->map(fn (Bill $bill) => [
            $bill->at()->format('d/m/Y H:i'),
            $bill->title(),
            $bill->billNumber() ?? '',
            $bill->subtotal(),
            $bill->discount(),
            $bill->total(),
            $bill->paidAt()?->format('d/m/Y H:i') ?? __('cashier.unpaid'),
            $bill->payer() ?? '',
            $bill->customer() ?? '',
        ]));
    }

    /**
     * @param  array<string, list<string>>  $filters
     * @return Collection<int, Bill>
     */
    private function bills(CarbonImmutable $from, CarbonImmutable $to, string $search, array $filters): Collection
    {
        $one = fn (string $name) => count($filters[$name]) === 1 ? $filters[$name][0] : null;
        $needle = mb_strtolower($search);

        return Cashier::between($from, $to->endOfDay())
            ->filter(fn (Bill $bill) => match ($one('paid')) {
                'paid' => $bill->paidAt() !== null,
                'unpaid' => $bill->paidAt() === null,
                default => true,
            })
            ->filter(fn (Bill $bill) => match ($one('number')) {
                'has' => $bill->billNumber() !== null,
                'missing' => $bill->billNumber() === null,
                default => true,
            })
            ->filter(fn (Bill $bill) => match ($one('source')) {
                'dinein' => $bill->isTable(),
                'takeaway' => ! $bill->isTable(),
                default => true,
            })
            ->filter(fn (Bill $bill) => $needle === ''
                || str_contains(mb_strtolower((string) $bill->billNumber()), $needle)
                || str_contains(mb_strtolower($bill->title()), $needle))
            ->values();
    }

    /** @return array<string, list<string>> */
    private function filters(): array
    {
        return collect(self::FILTERS)->map(fn (array $allowed, string $name) => filter_values($name, $allowed))->all();
    }

    /** @return array{CarbonImmutable, CarbonImmutable} mặc định hôm nay */
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
        $from = $parse('from') ?? $to;

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return [$from, $to];
    }
}
