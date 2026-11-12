<?php

namespace App\Modules\Takeaway;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Kitchen\Kitchen;
use App\Modules\Menu\ChosenOptions;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\ModuleManager;
use App\Modules\Takeaway\Models\DiscountCode;
use App\Modules\Takeaway\Models\TakeawayOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    public function __construct(private ModuleManager $modules) {}

    /**
     * @param  list<array{item: int, quantity: int, options?: list<int>, note?: ?string, line?: ?int}>  $lines  các món đã chọn
     * @param  array{customer_name: string, customer_phone: ?string, pickup_at: mixed, notes?: ?string, locale?: string, discount_mode?: ?string, discount_code?: ?string, discount_percent?: mixed, discount_amount?: mixed}  $data
     */
    public function handle(array $lines, array $data, ?User $user = null): TakeawayOrder
    {
        $lines = $this->filled($lines);

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('takeaway.no_items')]);
        }

        $pickup = CarbonImmutable::parse($data['pickup_at']);
        $this->assertPickupTime($pickup);

        return DB::transaction(function () use ($lines, $data, $user, $pickup): TakeawayOrder {
            // Khoá hàng của quán để số đơn tăng dần không trùng.
            Restaurant::whereKey(tenant()->getKey())->lockForUpdate()->first();

            $built = $this->build($lines);
            $subtotal = array_sum(array_map(fn (array $line): int => $line['unit_price'] * $line['quantity'], $built));
            $discount = $this->discount($data, $subtotal);

            $order = TakeawayOrder::create([
                'customer_id' => Customer::remember($data['customer_name'], $data['customer_phone'])->getKey(),
                'created_by' => $user?->getKey(),
                'number' => $this->nextNumber($pickup),
                'code_prefix' => $this->modules->settings(tenant(), 'takeaway')['order_code_prefix'],
                'pickup_date' => $pickup->toDateString(),
                'status' => OrderStatus::New,
                'pickup_at' => $pickup,
                'subtotal' => $subtotal,
                ...$discount,
                'total' => $subtotal - $discount['discount_amount'],
                'notes' => $data['notes'] ?? null,
                'locale' => $data['locale'] ?? 'vi',
            ]);

            foreach ($built as $line) {
                $order->items()->create(['restaurant_id' => $order->restaurant_id, ...$line]);
            }

            $this->syncVoucher($order, null);

            return $order;
        });
    }

    /**
     * Sửa đơn. Món đã có (cùng món, cùng tuỳ chọn) giữ giá lúc đặt, chỉ đổi số lượng và ghi chú;
     * món mới hoặc đổi tuỳ chọn lấy giá hiện tại. Giảm giá giữ nguyên cách tính cũ và tính lại trên tạm tính mới.
     *
     * @param  list<array{item: int, quantity: int, options?: list<int>, note?: ?string, line?: ?int}>  $lines
     * @param  array<string, mixed>  $data
     */
    public function update(TakeawayOrder $order, array $lines, array $data, ?User $user = null): TakeawayOrder
    {
        $lines = $this->filled($lines);

        $pickup = CarbonImmutable::parse($data['pickup_at']);

        if (! $pickup->equalTo($order->pickup_at)) {
            $this->assertPickupTime($pickup);
        }

        return DB::transaction(function () use ($order, $lines, $data, $user, $pickup): TakeawayOrder {
            // Cùng thứ tự khoá với lúc tạo đơn, vì đổi ngày nhận cũng cấp số mới.
            Restaurant::whereKey(tenant()->getKey())->lockForUpdate()->first();

            $order = TakeawayOrder::whereKey($order->getKey())->lockForUpdate()->with(['items', 'customer'])->firstOrFail();

            if (! $order->isEditable()) {
                throw ValidationException::withMessages(['lines' => __('takeaway.not_editable')]);
            }

            $existing = $order->items->whereNotNull('menu_item_id')->keyBy('id');
            $detached = $order->items->whereNull('menu_item_id');

            if ($lines === [] && $detached->isEmpty()) {
                throw ValidationException::withMessages(['lines' => __('takeaway.no_items')]);
            }

            // Dòng nào còn là món cũ (cùng món, cùng tuỳ chọn) thì giữ, còn lại là món mới.
            $kept = [];
            $fresh = [];

            foreach ($lines as $row) {
                $line = isset($row['line']) ? $existing->get((int) $row['line']) : null;
                $picked = array_map('intval', $row['options'] ?? []);
                sort($picked);

                if ($line && ! isset($kept[$line->id]) && $line->menu_item_id === (int) $row['item'] && $line->optionIds() === $picked) {
                    $kept[$line->id] = $row;
                } else {
                    $fresh[] = $row;
                }
            }

            $built = $this->build($fresh);
            $changes = [];

            foreach ($existing as $id => $line) {
                if (! isset($kept[$id])) {
                    $changes[] = __('takeaway.change_removed', ['name' => $line->label()]);
                    $line->delete();

                    continue;
                }

                $quantity = (int) $kept[$id]['quantity'];
                $note = $this->note($kept[$id]);

                if ($quantity !== $line->quantity) {
                    $changes[] = __('takeaway.change_quantity', ['name' => $line->label(), 'from' => $line->quantity, 'to' => $quantity]);
                }

                if ($note !== $line->note) {
                    $changes[] = __('takeaway.change_line_note', ['name' => $line->label()]);
                }

                $line->update(['quantity' => $quantity, 'note' => $note]);
            }

            foreach ($built as $line) {
                $created = $order->items()->create(['restaurant_id' => $order->restaurant_id, ...$line]);
                $changes[] = __('takeaway.change_added', ['quantity' => $created->quantity, 'name' => $created->label()]);
            }

            $oldCustomer = $order->customer->name;
            $customer = $this->customerFor($order, $data);
            $subtotal = $order->items()->get()->sum(fn ($line): int => $line->lineTotal());
            $discount = $this->discount($data, $subtotal, $order);
            $total = $subtotal - $discount['discount_amount'];

            if ($customer->getKey() !== $order->customer_id || $customer->name !== $oldCustomer) {
                $changes[] = __('takeaway.change_customer', ['from' => $oldCustomer, 'to' => $customer->name]);
            }

            if (! $pickup->equalTo($order->pickup_at)) {
                $changes[] = __('takeaway.change_pickup', [
                    'from' => $order->pickup_at->format('H:i d/m'),
                    'to' => $pickup->format('H:i d/m'),
                ]);
            }

            if (($data['notes'] ?? null) !== $order->notes) {
                $changes[] = __('takeaway.change_notes');
            }

            if ($total !== $order->total) {
                $changes[] = __('takeaway.change_total', ['from' => vnd($order->total), 'to' => vnd($total)]);
            }

            $oldCode = $order->code();
            $oldVoucher = $order->customer_offer_id;
            $movedDay = $pickup->toDateString() !== $order->pickup_date->toDateString();

            $order->update([
                'customer_id' => $customer->getKey(),
                'number' => $movedDay ? $this->nextNumber($pickup) : $order->number,
                'pickup_date' => $pickup->toDateString(),
                'pickup_at' => $pickup,
                'subtotal' => $subtotal,
                ...$discount,
                'total' => $total,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncVoucher($order, $oldVoucher);

            if ($movedDay) {
                $changes[] = __('takeaway.change_code', ['from' => $oldCode, 'to' => $order->code()]);
            }

            if ($changes !== []) {
                $order->edits()->create([
                    'restaurant_id' => $order->restaurant_id,
                    'user_id' => $user?->getKey(),
                    'changes' => $changes,
                ]);

                ActivityLog::record($order->restaurant_id, 'takeaway', __('activity.order_edited', ['code' => $order->code()]), $changes, $order);
            }

            return $order;
        });
    }

    /**
     * Bỏ dòng số lượng 0, đánh lại chỉ số.
     *
     * @return list<array<string, mixed>>
     */
    private function filled(array $lines): array
    {
        return array_values(array_filter($lines, fn ($row): bool => is_array($row) && (int) ($row['quantity'] ?? 0) > 0));
    }

    /**
     * Món mới: món phải đang bán mang đi, kiểm tra tuỳ chọn, chép tên và giá hiện tại.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function build(array $rows): array
    {
        $items = MenuItem::orderableForTakeaway()->with('optionGroups.options')
            ->whereKey(array_map(fn (array $row): int => (int) $row['item'], $rows))
            ->get()
            ->keyBy('id');
        $fail = fn (string $message) => throw ValidationException::withMessages(['lines' => $message]);
        $built = [];

        foreach ($rows as $row) {
            $item = $items->get((int) $row['item']) ?? $fail(__('takeaway.item_unavailable'));
            $chosen = ChosenOptions::resolve($item, $row['options'] ?? [], $fail, 'takeaway');

            $built[] = [
                'menu_item_id' => $item->getKey(),
                'dinein_station_id' => $station = Kitchen::stationFor($item),
                'name' => $item->name,
                'unit_price' => $item->price_takeaway + array_sum(array_column($chosen, 'price')),
                'quantity' => (int) $row['quantity'],
                'options' => $chosen ?: null,
                'note' => $this->note($row),
                // Món không qua màn hình khu thì coi như đã xong ngay, chỉ còn đóng gói.
                'done_at' => $station === null ? now() : null,
            ];
        }

        return $built;
    }

    private function note(array $row): ?string
    {
        $note = trim((string) ($row['note'] ?? ''));

        return $note === '' ? null : $note;
    }

    /**
     * Khách của đơn khi sửa. Không có số điện thoại thì không tra được khách cũ:
     * đơn đang là khách không số thì giữ khách đó (đổi tên nếu cần), không tạo khách mới mỗi lần lưu.
     */
    private function customerFor(TakeawayOrder $order, array $data): Customer
    {
        if (blank($data['customer_phone'] ?? null) && $order->customer->phone === null) {
            if ($order->customer->name !== $data['customer_name']) {
                $order->customer->update(['name' => $data['customer_name']]);
            }

            return $order->customer;
        }

        return Customer::remember($data['customer_name'], $data['customer_phone'] ?? null);
    }

    /** Gọi trong transaction đã khoá hàng của quán. */
    private function nextNumber(CarbonImmutable $pickup): int
    {
        return (int) TakeawayOrder::where('pickup_date', $pickup->toDateString())->max('number') + 1;
    }

    public function changeStatus(TakeawayOrder $order, OrderStatus $status): TakeawayOrder
    {
        return DB::transaction(function () use ($order, $status): TakeawayOrder {
            $order = TakeawayOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $order->status->canBecome($status)) {
                throw ValidationException::withMessages(['status' => __('takeaway.bad_transition')]);
            }

            $order->update(['status' => $status]);

            // Huỷ đơn thì ưu đãi khách đã dùng được trả lại.
            if ($status === OrderStatus::Cancelled && $order->customer_offer_id) {
                CustomerOffer::find($order->customer_offer_id)?->release();
            }

            return $order;
        });
    }

    /** Ưu đãi lần sau (voucher) chỉ dùng một lần: đơn dùng mã thì đánh dấu đã dùng, đổi hay bỏ mã thì trả lại. */
    private function syncVoucher(TakeawayOrder $order, ?int $old): void
    {
        $new = $order->customer_offer_id;

        if ($old === $new) {
            return;
        }

        if ($old !== null) {
            CustomerOffer::find($old)?->release();
        }

        if ($new !== null) {
            $offer = CustomerOffer::whereKey($new)->lockForUpdate()->first();

            if (! $offer?->isUsable()) {
                throw ValidationException::withMessages(['discount_code' => __('takeaway.code_invalid')]);
            }

            $offer->markUsed(order: $order);
        }
    }

    /** @return array{discount_type: ?DiscountType, discount_value: ?int, discount_code: ?string, discount_amount: int} */
    private function discount(array $data, int $subtotal, ?TakeawayOrder $order = null): array
    {
        [$type, $value, $code, $offer] = match ($data['discount_mode'] ?? 'none') {
            'percent' => [DiscountType::Percent, (int) $data['discount_percent'], null, null],
            'amount' => [DiscountType::Amount, (int) $data['discount_amount'], null, null],
            'code' => $this->fromCode($data['discount_code'] ?? null, $order),
            default => [null, null, null, null],
        };

        return [
            'discount_type' => $type,
            'discount_value' => $value,
            'discount_code' => $code,
            'customer_offer_id' => $offer,
            'discount_amount' => $type?->amountOff($value, $subtotal) ?? 0,
        ];
    }

    /**
     * Mã khách đưa: ưu đãi lần sau (voucher, dùng một lần) hoặc mã giảm giá dùng chung.
     *
     * @return array{DiscountType, int, string, ?int}
     */
    private function fromCode(?string $code, ?TakeawayOrder $order = null): array
    {
        // Mã đã áp dụng cho đơn vẫn giữ, kể cả khi sau đó mã bị tắt hoặc đổi mức giảm.
        if ($order?->discount_code && strtoupper(trim((string) $code)) === $order->discount_code) {
            return [$order->discount_type, $order->discount_value, $order->discount_code, $order->customer_offer_id];
        }

        if ($voucher = CustomerOffer::usableCode($code)) {
            return [$voucher->type, $voucher->value, $voucher->code, $voucher->id];
        }

        $discount = DiscountCode::usable($code)
            ?? throw ValidationException::withMessages(['discount_code' => __('takeaway.code_invalid')]);

        return [$discount->type, $discount->value, $discount->code, null];
    }

    private function assertPickupTime(CarbonImmutable $pickup): void
    {
        $settings = $this->modules->settings(tenant(), 'takeaway');
        $time = $pickup->format('H:i');

        if ($time < $settings['pickup_opens'] || $time > $settings['pickup_closes']) {
            throw ValidationException::withMessages(['pickup_at' => __('takeaway.outside_pickup', [
                'opens' => $settings['pickup_opens'],
                'closes' => $settings['pickup_closes'],
            ])]);
        }
    }
}
