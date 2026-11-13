<?php

namespace App\Modules\Cashier;

use App\Models\ActivityLog;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\OrderStatus;
use App\Support\BillNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Thu ngân: phiếu bàn đã đóng và đơn mang đi chờ thu ở quầy, thu tiền, đối soát.
 * Quán không bật Thu ngân thì đóng bàn, giao đơn là coi như đã thu.
 */
class Cashier
{
    private const TABLE_WITH = ['lines', 'customer', 'payer', 'closer'];

    private const ORDER_WITH = ['items', 'customer', 'payer'];

    public static function enabled(?Restaurant $restaurant = null): bool
    {
        return (bool) ($restaurant ?? tenant())?->hasModule('cashier');
    }

    private static function on(string $module): bool
    {
        return (bool) tenant()?->hasModule($module);
    }

    /** Chờ thu tiền: bàn đã đóng chưa thu, đơn mang đi xong hoặc đã giao chưa thu. Cũ nhất lên đầu. */
    public static function pending(): Collection
    {
        $tables = self::on('dinein')
            ? DiningTable::with(self::TABLE_WITH)->whereNotNull('closed_at')->whereNull('paid_at')->get()
            : collect();
        $orders = self::on('takeaway')
            ? TakeawayOrder::with(self::ORDER_WITH)->whereNull('paid_at')->whereIn('status', [OrderStatus::Ready, OrderStatus::Delivered])->get()
            : collect();

        return Bill::wrap([...$tables, ...$orders])->sortBy(fn (Bill $bill) => $bill->at()->timestamp)->values();
    }

    /** Bàn đang phục vụ (có món, chưa thu): khách ra quầy trước khi phục vụ kịp đóng bàn. */
    public static function openTables(): Collection
    {
        if (! self::on('dinein')) {
            return collect();
        }

        return Bill::wrap(DiningTable::open()->with(self::TABLE_WITH)->whereNull('paid_at')->has('lines')->orderBy('label')->get());
    }

    /** Đã thu hôm nay, mới nhất lên đầu. */
    public static function paidToday(): Collection
    {
        return self::paidBetween(CarbonImmutable::today(), CarbonImmutable::today()->endOfDay())
            ->sortByDesc(fn (Bill $bill) => $bill->paidAt()->timestamp)->values();
    }

    /** Phiếu trong khoảng ngày (đối soát): bàn theo giờ đóng, đơn mang đi theo giờ nhận; bỏ đơn huỷ. */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $tables = self::on('dinein')
            ? DiningTable::with(self::TABLE_WITH)->whereBetween('closed_at', [$from, $to])->get()
            : collect();
        $orders = self::on('takeaway')
            ? TakeawayOrder::with(self::ORDER_WITH)->whereBetween('pickup_at', [$from, $to])->where('status', '!=', OrderStatus::Cancelled)->get()
            : collect();

        return Bill::wrap([...$tables, ...$orders])->sortByDesc(fn (Bill $bill) => $bill->at()->timestamp)->values();
    }

    private static function paidBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $tables = self::on('dinein') ? DiningTable::with(self::TABLE_WITH)->whereBetween('paid_at', [$from, $to])->get() : collect();
        $orders = self::on('takeaway') ? TakeawayOrder::with(self::ORDER_WITH)->whereBetween('paid_at', [$from, $to])->get() : collect();

        return Bill::wrap([...$tables, ...$orders]);
    }

    /** Tìm phiếu theo khoá "t12" / "o5". */
    public static function find(string $key): Bill
    {
        abort_unless(preg_match('/^([to])(\d+)$/', $key, $match), 404);

        $model = $match[1] === 't'
            ? DiningTable::with(self::TABLE_WITH)->findOrFail($match[2])
            : TakeawayOrder::with(self::ORDER_WITH)->findOrFail($match[2]);

        return new Bill($model);
    }

    /** Đã thu tiền: ghi giờ, người thu, số hoá đơn máy tính tiền (không bắt buộc). */
    public static function collect(Bill $bill, ?string $number, User $user): void
    {
        DB::transaction(function () use ($bill, $number, $user): void {
            $model = $bill->model->newQuery()->whereKey($bill->model->getKey())->lockForUpdate()->firstOrFail();

            if ($model->paid_at !== null) {
                throw ValidationException::withMessages(['bill_number' => __('cashier.already_paid')]);
            }

            if ($model instanceof TakeawayOrder && $model->status === OrderStatus::Cancelled) {
                abort(404);
            }

            $model->update(['paid_at' => now(), 'paid_by' => $user->id, 'bill_number' => BillNumber::clean($number)]);
            $bill->model->setRawAttributes($model->getAttributes(), true);
        });

        ActivityLog::record($bill->model->restaurant_id, 'cashier', __('cashier.log_paid', ['bill' => $bill->title(), 'total' => vnd($bill->total())]),
            array_filter([$bill->billNumber() ? __('dinein.log_bill_number', ['number' => $bill->billNumber()]) : null]), $bill->model);
    }

    /** Nhập hoặc sửa số hoá đơn sau khi đã thu (quên nhập, nhập sai). */
    public static function updateNumber(Bill $bill, ?string $number): void
    {
        abort_if($bill->paidAt() === null, 404);

        $old = $bill->billNumber();
        $bill->model->update(['bill_number' => BillNumber::clean($number)]);
        ActivityLog::record($bill->model->restaurant_id, 'cashier', __('cashier.log_number', ['bill' => $bill->title()]),
            [($old ?? '—').' → '.($bill->billNumber() ?? '—')], $bill->model);
    }

    /**
     * Quán không bật Thu ngân, hoặc phiếu 0 đồng: đóng bàn / giao đơn là coi như đã thu.
     */
    public static function settleWithoutCashier(DiningTable|TakeawayOrder $model, User $user, int $total): void
    {
        if ($model->paid_at === null && (! self::enabled() || $total <= 0)) {
            $model->update(['paid_at' => now(), 'paid_by' => $user->id]);
        }
    }
}
