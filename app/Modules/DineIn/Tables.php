<?php

namespace App\Modules\DineIn;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\User;
use App\Modules\Cashier\Cashier;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationBooking;
use App\Modules\Reservation\ReservationStatus;
use App\Push\Notify;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Số bàn (A12 = khu A, bàn 12) và xếp khách vào bàn. */
class Tables
{
    /** @return Collection<int, Area> khu vực đang dùng và có mã */
    public static function areas(): Collection
    {
        return Area::where('is_active', true)->whereNotNull('code')->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Tách A12 thành khu vực A và bàn số 12, bàn phải nằm trong số bàn của khu.
     * Không có $errorKey thì trả 404, có thì báo lỗi ở ô đó.
     *
     * @return array{Area, int}
     */
    public static function resolve(string $label, ?string $errorKey = null): array
    {
        preg_match('/^([A-Za-z]{1,2})([0-9]{1,3})$/', $label, $match);
        $area = $match ? Area::where('is_active', true)->where('code', strtoupper($match[1]))->first() : null;
        $number = (int) ($match[2] ?? 0);

        if ($area === null || $number < 1 || $number > $area->table_count) {
            if ($errorKey === null) {
                abort(404);
            }

            throw ValidationException::withMessages([$errorKey => $area === null
                ? __('dinein.table_unknown_area', ['code' => strtoupper($match[1] ?? $label)])
                : __('dinein.table_out_of_range', ['area' => $area->name, 'count' => $area->table_count, 'code' => $area->code])]);
        }

        return [$area, $number];
    }

    /** Người này xếp được khách đặt trước vào bàn: quán bật Lấy đơn tại bàn và có quyền gọi món. */
    public static function canSeat(?User $user): bool
    {
        return $user !== null && (bool) $user->restaurant?->hasModule('dinein') && $user->can('dinein.order');
    }

    /**
     * Bàn xếp được khách: bàn trống, hoặc bàn đang phục vụ nhưng chưa gắn khách.
     *
     * @return array<string, string> [A12 => "A12 · Trong nhà"]
     */
    public static function seatOptions(): array
    {
        $taken = DiningTable::open()->get(['area_id', 'table_number', 'customer_id'])
            ->keyBy(fn (DiningTable $table) => $table->area_id.'-'.$table->table_number);
        $options = [];

        foreach (self::areas() as $area) {
            for ($number = 1; $number <= $area->table_count; $number++) {
                $open = $taken->get($area->id.'-'.$number);

                if ($open?->customer_id) {
                    continue;
                }

                $label = $area->tableLabel($number);
                $options[$label] = $label.' · '.$area->name.($open ? ' · '.__('dinein.seat_busy') : '');
            }
        }

        return $options;
    }

    /** Bàn đang gắn khách khác thì không xếp thêm khách vào được. */
    public static function assertFree(?DiningTable $table, Customer $customer, string $errorKey = 'table'): void
    {
        if ($table?->customer_id && $table->customer_id !== $customer->id) {
            throw ValidationException::withMessages([$errorKey => __('dinein.seat_taken', ['table' => $table->label])]);
        }
    }

    /**
     * Xếp khách vào bàn: bàn trống thì mở lượt mới, bàn đang phục vụ chưa có khách thì gắn khách vào.
     */
    public static function seat(Area $area, int $number, Customer $customer, ?Reservation $reservation, User $user): DiningTable
    {
        $table = DB::transaction(function () use ($area, $number, $customer, $reservation, $user): DiningTable {
            $table = DiningTable::open()->where('area_id', $area->id)->where('table_number', $number)->lockForUpdate()->first();

            self::assertFree($table, $customer);

            $values = ['customer_id' => $customer->id, 'reservation_id' => $reservation?->id];

            if ($table) {
                $table->update($values);

                return $table;
            }

            return DiningTable::create([
                ...$values,
                'area_id' => $area->id,
                'table_number' => $number,
                'label' => $area->tableLabel($number),
                'opened_by' => $user->id,
            ]);
        });

        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_seated', ['table' => $table->label, 'name' => $customer->name]), [], $table);

        return $table;
    }

    /**
     * Đóng bàn: giảm giá tính theo tạm tính lúc đóng và lưu lại; bật Thu ngân thì phiếu chuyển sang quầy chờ thu.
     * Khách đặt trước về rồi thì đặt bàn chuyển sang Hoàn tất. Còn món chưa mang ra thì báo lỗi.
     */
    public static function close(DiningTable $table, User $user): void
    {
        if ($table->hasPendingLines()) {
            throw ValidationException::withMessages(['table' => __('dinein.close_pending')]);
        }

        $discount = $table->discount();
        $table->update(['closed_at' => now(), 'closed_by' => $user->id, 'discount_amount' => $discount]);
        $total = $table->subtotal() - $discount;
        Cashier::settleWithoutCashier($table, $user, $total);

        // Bàn chuyển sang quầy chờ thu: báo người thu tiền.
        if ($table->paid_at === null) {
            Notify::permitted('cashier.collect', [
                'title' => __('cashier.push_pending', ['table' => $table->label]),
                'body' => __('cashier.push_pending_body', ['total' => vnd($total)]),
                'url' => route('tenant.admin.cashier.index'),
                'tag' => 'cashier-t'.$table->getKey(),
            ]);
        }
        LineLog::closed($table->load('customer'), $table->subtotal(), $discount, (int) $table->lines()->where('status', LineStatus::Served)->sum('quantity'));

        if ($table->reservation?->status === ReservationStatus::Seated) {
            app(ReservationBooking::class)->changeStatus($table->reservation, ReservationStatus::Completed, $user);
        }
    }
}
