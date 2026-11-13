<?php

namespace App\Modules\DineIn;

use App\Models\ActivityLog;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\DineIn\Models\OrderLine;
use Illuminate\Support\Collection;

/**
 * Nhật ký món tại bàn: gọi món, khu báo xong, mang ra, huỷ, đóng bàn. Người bấm
 * được nhật ký tự ghi; mỗi dòng ghi món, tuỳ chọn, ghi chú, khu và các mốc giờ.
 */
final class LineLog
{
    /** "2 × Phở bò tái (Thịt: Sườn; Ít cơm)" */
    public static function describe(OrderLine $line): string
    {
        $extra = [...$line->optionLines(), ...($line->note ? [$line->note] : [])];

        return $line->quantity.' × '.$line->name.($extra !== [] ? ' ('.implode('; ', $extra).')' : '');
    }

    /** Các mốc giờ của món: gọi, xong, mang ra, huỷ. */
    public static function times(OrderLine $line): string
    {
        return collect([
            __('dinein.log_at_ordered', ['time' => $line->created_at->format('H:i')]),
            $line->done_at && $line->dinein_station_id ? __('dinein.log_at_done', ['time' => $line->done_at->format('H:i')]) : null,
            $line->served_at ? __('dinein.log_at_served', ['time' => $line->served_at->format('H:i')]) : null,
            $line->cancelled_at ? __('dinein.log_at_cancelled', ['time' => $line->cancelled_at->format('H:i')]) : null,
        ])->filter()->implode(' · ');
    }

    /** @param Collection<int, OrderLine> $lines */
    public static function ordered(DiningTable $table, int $round, Collection $lines): void
    {
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_ordered', ['table' => $table->label, 'round' => $round, 'count' => $lines->sum('quantity')]),
            $lines->map(fn (OrderLine $line) => self::describe($line).' · '.($line->station?->name ?? __('dinein.log_direct')))->all(), $table);
    }

    /** @param Collection<int, OrderLine> $lines cùng một bàn */
    public static function done(string $station, Collection $lines): void
    {
        $table = $lines->first()->diningTable;

        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_done', ['station' => $station, 'table' => $table->label, 'count' => $lines->sum('quantity')]),
            $lines->map(fn (OrderLine $line) => self::describe($line).' · '.self::times($line))->all(), $table);
    }

    public static function served(OrderLine $line): void
    {
        ActivityLog::record($line->restaurant_id, 'dinein', __('dinein.log_served', ['table' => $line->diningTable->label, 'name' => self::describe($line)]),
            [self::times($line)], $line->diningTable);
    }

    public static function cancelled(OrderLine $line, LineStatus $before): void
    {
        ActivityLog::record($line->restaurant_id, 'dinein', __('dinein.log_cancelled', ['table' => $line->diningTable->label, 'name' => self::describe($line)]),
            [__('dinein.log_before', ['status' => $before->label()]), self::times($line)], $line->diningTable);
    }

    public static function closed(DiningTable $table, int $subtotal, int $discount, int $count): void
    {
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_closed', ['table' => $table->label]), array_values(array_filter([
            __('dinein.log_opened_at', ['time' => $table->created_at->format('H:i d/m')]),
            __('dinein.log_items', ['count' => $count]),
            __('dinein.log_total', ['total' => vnd($subtotal)]),
            $discount > 0 ? __('dinein.log_discount', ['amount' => vnd($discount)]) : null,
            $table->customer ? __('dinein.log_customer', ['name' => $table->customer->name]) : null,
            $table->bill_number ? __('dinein.log_bill_number', ['number' => $table->bill_number]) : null,
        ])), $table);
    }
}
