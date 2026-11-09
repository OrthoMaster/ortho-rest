<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Takeaway\Models\TakeawayOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Thông tin khách chỉ giữ trong một thời hạn tính từ lần cuối khách đặt bàn
 * hoặc nhận đơn mang đi. Hết hạn thì ẩn danh: xoá tên, số điện thoại, email,
 * ghi chú, và gỡ tên, số khỏi lịch sử sửa và nhật ký. Lượt đặt vẫn giữ để
 * thống kê của quán không bị hỏng.
 */
class CustomerRetention
{
    public const DAYS = [30, 60, 90];

    public const DEFAULT_DAYS = 60;

    /** Báo trước bao nhiêu ngày. */
    public const NOTICE_DAYS = 7;

    /** Lần cuối có việc với quán: lượt đặt bàn hay đơn mang đi gần nhất, kể cả trong tương lai. */
    public static function lastSeenSql(): string
    {
        return 'GREATEST(customers.created_at, '
            .'COALESCE((select max(reserved_at) from reservations where reservations.customer_id = customers.id), customers.created_at), '
            .'COALESCE((select max(pickup_at) from takeaway_orders where takeaway_orders.customer_id = customers.id), customers.created_at))';
    }

    public static function days(Restaurant $restaurant): int
    {
        return in_array((int) $restaurant->customer_retention_days, self::DAYS, true) ? (int) $restaurant->customer_retention_days : self::DEFAULT_DAYS;
    }

    /** Khách còn thông tin (hoặc mọi khách), kèm lần cuối có việc với quán (last_seen_at). */
    public static function query(bool $withAnonymized = false): Builder
    {
        return Customer::query()
            ->when(! $withAnonymized, fn ($query) => $query->whereNull('anonymized_at'))
            ->select('customers.*')
            ->selectRaw(self::lastSeenSql().' as last_seen_at');
    }

    /** Khách sẽ bị ẩn danh trước thời điểm này. */
    public static function dueBefore(Restaurant $restaurant, CarbonImmutable $moment): Builder
    {
        return Customer::query()
            ->whereNull('anonymized_at')
            ->whereRaw(self::lastSeenSql().' < ?', [$moment->subDays(self::days($restaurant))]);
    }

    public static function deleteDate(Restaurant $restaurant, mixed $lastSeen): CarbonImmutable
    {
        return CarbonImmutable::parse($lastSeen)->addDays(self::days($restaurant));
    }

    /** Ẩn danh mọi khách đã quá hạn. Gọi khi có người mở trang quản lý, mỗi ngày một lần. */
    public static function prune(Restaurant $restaurant): int
    {
        $count = 0;

        self::dueBefore($restaurant, CarbonImmutable::now())->orderBy('id')->each(function (Customer $customer) use (&$count): void {
            self::anonymize($customer);
            $count++;
        });

        if ($count > 0) {
            ActivityLog::record($restaurant->getKey(), 'customers', __('customers.pruned', ['count' => $count, 'days' => self::days($restaurant)]));
        }

        return $count;
    }

    public static function anonymize(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $needles = array_values(array_filter([$customer->name, $customer->phone, $customer->email], fn ($value) => filled($value)));
            $placeholder = __('customers.anonymous');
            $scrub = fn (?string $text): ?string => $text === null ? null : str_replace($needles, $placeholder, $text);

            $reservations = DB::table('reservations')->where('customer_id', $customer->getKey())->pluck('id');
            $orders = DB::table('takeaway_orders')->where('customer_id', $customer->getKey())->pluck('id');

            DB::table('reservations')->whereIn('id', $reservations)->update(['notes' => null]);
            DB::table('takeaway_orders')->whereIn('id', $orders)->update(['notes' => null]);

            $scrubRows = function (string $table, string $column, $ids, array $textColumns) use ($scrub): void {
                DB::table($table)->whereIn($column, $ids)->orderBy('id')->each(function ($row) use ($table, $textColumns, $scrub): void {
                    $values = [];

                    foreach ($textColumns as $name => $json) {
                        $values[$name] = $json
                            ? json_encode(array_map($scrub, json_decode($row->{$name}, true) ?? []), JSON_UNESCAPED_UNICODE)
                            : $scrub($row->{$name});
                    }

                    DB::table($table)->where('id', $row->id)->update($values);
                });
            };

            $scrubRows('reservation_edits', 'reservation_id', $reservations, ['changes' => true]);
            $scrubRows('takeaway_order_edits', 'takeaway_order_id', $orders, ['changes' => true]);

            foreach ([[(new Reservation)->getMorphClass(), $reservations], [(new TakeawayOrder)->getMorphClass(), $orders]] as [$type, $ids]) {
                DB::table('activity_logs')->where('subject_type', $type)->whereIn('subject_id', $ids)->orderBy('id')
                    ->each(function ($log) use ($scrub): void {
                        DB::table('activity_logs')->where('id', $log->id)->update([
                            'summary' => mb_substr($scrub($log->summary), 0, 255),
                            'changes' => json_encode(array_map($scrub, json_decode($log->changes, true) ?? []), JSON_UNESCAPED_UNICODE),
                        ]);
                    });
            }

            DB::table('customers')->where('id', $customer->getKey())->update([
                'name' => $placeholder,
                'phone' => null,
                'email' => null,
                'anonymized_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
