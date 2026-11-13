<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\DineIn\Models\DiningTable;
use App\Support\BillNumber;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Phiếu đã đóng: bàn đã đóng theo ngày, kèm số hoá đơn của máy tính tiền,
 * để đối soát doanh thu máy tính tiền với Liresto. Nhập hoặc sửa số hoá đơn sau khi đóng.
 */
class BillController extends Controller
{
    public const MAX_DAYS = 31;

    public const NUMBER_FILTERS = ['has', 'missing'];

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $search = trim((string) $request->query('q'));
        $numbers = filter_values('number', self::NUMBER_FILTERS);
        $query = $this->query($from, $to, $search, $numbers);

        // Tổng của cả khoảng lọc (không chỉ trang đang xem).
        $totals = DB::query()->fromSub((clone $query)->reorder(), 'bills')
            ->selectRaw('count(*) as bills, coalesce(sum(bill_subtotal - discount_amount), 0) as revenue, sum(bill_number is null) as missing')
            ->first();

        return view('modules.dinein.bills', [
            'bills' => $query->paginate(50)->withQueryString(),
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'numbers' => $numbers,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->range($request);
        $bills = $this->query($from, $to, trim((string) $request->query('q')), filter_values('number', self::NUMBER_FILTERS))->get();

        return SimpleXlsx::download(__('dinein.bills_csv').'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx', [
            __('dinein.bill_closed_at'), __('dinein.table_label'), __('dinein.bill_number'), __('dinein.bill_subtotal'),
            __('dinein.bill_discount'), __('dinein.bill_total'), __('dinein.bill_closed_by'), __('reservation.customer_name'),
        ], $bills->map(fn (DiningTable $table) => [
            $table->closed_at->format('d/m/Y H:i'),
            $table->label,
            $table->bill_number ?? '',
            (int) $table->bill_subtotal,
            (int) $table->discount_amount,
            (int) $table->bill_subtotal - (int) $table->discount_amount,
            $table->closer?->name ?? '',
            $table->customer?->name ?? '',
        ]));
    }

    /** Nhập hoặc sửa số hoá đơn sau khi đã đóng bàn (quên nhập, nhập sai). */
    public function update(Request $request, DiningTable $table): RedirectResponse
    {
        abort_if($table->isOpen(), 404);

        $number = BillNumber::clean($request->validateWithBag('bill-'.$table->id, ['bill_number' => BillNumber::RULES])['bill_number'] ?? null);
        $old = $table->bill_number;
        $table->update(['bill_number' => $number]);
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_bill_number_changed', ['table' => $table->label]), [($old ?? '—').' → '.($number ?? '—')], $table);

        return back()->with('status', __('dinein.bills_saved', ['table' => $table->label]));
    }

    /** @param  array<int, string>  $numbers */
    private function query(CarbonImmutable $from, CarbonImmutable $to, string $search, array $numbers): Builder
    {
        $subtotal = DB::table('dinein_lines')->selectRaw('coalesce(sum(unit_price * quantity), 0)')
            ->whereColumn('dinein_lines.dinein_table_id', 'dinein_tables.id')->where('status', '!=', 'cancelled');

        return DiningTable::with(['closer', 'customer'])
            ->select('dinein_tables.*')->selectSub($subtotal, 'bill_subtotal')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$from, $to->endOfDay()])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('bill_number', 'like', '%'.$search.'%')->orWhere('label', strtoupper($search))))
            ->when(count($numbers) === 1, fn (Builder $query) => $numbers[0] === 'has' ? $query->whereNotNull('bill_number') : $query->whereNull('bill_number'))
            ->latest('closed_at');
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
