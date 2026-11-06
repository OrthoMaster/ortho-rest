<?php

namespace App\Modules\Takeaway;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
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
     * @param  array<int, int>  $quantities  id món => số lượng
     * @param  array{customer_name: string, customer_phone: string, pickup_at: mixed, notes?: ?string, locale?: string, discount_mode?: ?string, discount_code?: ?string, discount_percent?: mixed, discount_amount?: mixed}  $data
     */
    public function handle(array $quantities, array $data, ?User $user = null): TakeawayOrder
    {
        $quantities = array_filter($quantities, fn (int $quantity): bool => $quantity > 0);

        if ($quantities === []) {
            throw ValidationException::withMessages(['items' => __('takeaway.no_items')]);
        }

        $pickup = CarbonImmutable::parse($data['pickup_at']);
        $this->assertPickupTime($pickup);

        return DB::transaction(function () use ($quantities, $data, $user, $pickup): TakeawayOrder {
            // Khoá hàng của quán để số đơn tăng dần không trùng.
            Restaurant::whereKey(tenant()->getKey())->lockForUpdate()->first();

            $items = MenuItem::orderableForTakeaway()->whereKey(array_keys($quantities))->get();

            if ($items->count() !== count($quantities)) {
                throw ValidationException::withMessages(['items' => __('takeaway.item_unavailable')]);
            }

            $subtotal = $items->sum(fn (MenuItem $item): int => $item->price_takeaway * $quantities[$item->getKey()]);
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

            foreach ($items as $item) {
                $order->items()->create([
                    'restaurant_id' => $order->restaurant_id,
                    'menu_item_id' => $item->getKey(),
                    'name' => $item->name,
                    'unit_price' => $item->price_takeaway,
                    'quantity' => $quantities[$item->getKey()],
                ]);
            }

            return $order;
        });
    }

    /**
     * Khách gọi sửa đơn. Món đã có giữ giá lúc đặt, món mới lấy giá hiện tại.
     * Giảm giá giữ nguyên cách tính cũ và tính lại trên tạm tính mới.
     *
     * @param  array<int, int>  $quantities
     * @param  array<string, mixed>  $data
     */
    public function update(TakeawayOrder $order, array $quantities, array $data, ?User $user = null): TakeawayOrder
    {
        $quantities = array_filter($quantities, fn (int $quantity): bool => $quantity > 0);

        $pickup = CarbonImmutable::parse($data['pickup_at']);

        if (! $pickup->equalTo($order->pickup_at)) {
            $this->assertPickupTime($pickup);
        }

        return DB::transaction(function () use ($order, $quantities, $data, $user, $pickup): TakeawayOrder {
            // Cùng thứ tự khoá với lúc tạo đơn, vì đổi ngày nhận cũng cấp số mới.
            Restaurant::whereKey(tenant()->getKey())->lockForUpdate()->first();

            $order = TakeawayOrder::whereKey($order->getKey())->lockForUpdate()->with(['items', 'customer'])->firstOrFail();

            if (! $order->isEditable()) {
                throw ValidationException::withMessages(['items' => __('takeaway.not_editable')]);
            }

            $existing = $order->items->whereNotNull('menu_item_id')->keyBy('menu_item_id');
            $detached = $order->items->whereNull('menu_item_id');

            if ($quantities === [] && $detached->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('takeaway.no_items')]);
            }

            $newIds = array_diff(array_keys($quantities), $existing->keys()->all());
            $newItems = MenuItem::orderableForTakeaway()->whereKey($newIds)->get();

            if ($newItems->count() !== count($newIds)) {
                throw ValidationException::withMessages(['items' => __('takeaway.item_unavailable')]);
            }

            $changes = [];

            foreach ($existing as $menuItemId => $line) {
                $quantity = $quantities[$menuItemId] ?? 0;

                if ($quantity === 0) {
                    $changes[] = __('takeaway.change_removed', ['name' => $line->name]);
                    $line->delete();
                } elseif ($quantity !== $line->quantity) {
                    $changes[] = __('takeaway.change_quantity', ['name' => $line->name, 'from' => $line->quantity, 'to' => $quantity]);
                    $line->update(['quantity' => $quantity]);
                }
            }

            foreach ($newItems as $item) {
                $changes[] = __('takeaway.change_added', ['quantity' => $quantities[$item->getKey()], 'name' => $item->name]);

                $order->items()->create([
                    'restaurant_id' => $order->restaurant_id,
                    'menu_item_id' => $item->getKey(),
                    'name' => $item->name,
                    'unit_price' => $item->price_takeaway,
                    'quantity' => $quantities[$item->getKey()],
                ]);
            }

            $customer = Customer::remember($data['customer_name'], $data['customer_phone']);
            $subtotal = $order->items()->get()->sum(fn ($line): int => $line->lineTotal());
            $discount = $this->discount($data, $subtotal, $order);
            $total = $subtotal - $discount['discount_amount'];

            if ($customer->getKey() !== $order->customer_id) {
                $changes[] = __('takeaway.change_customer', ['from' => $order->customer->name, 'to' => $customer->name]);
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

            return $order;
        });
    }

    /** @return array{discount_type: ?DiscountType, discount_value: ?int, discount_code: ?string, discount_amount: int} */
    private function discount(array $data, int $subtotal, ?TakeawayOrder $order = null): array
    {
        [$type, $value, $code] = match ($data['discount_mode'] ?? 'none') {
            'percent' => [DiscountType::Percent, (int) $data['discount_percent'], null],
            'amount' => [DiscountType::Amount, (int) $data['discount_amount'], null],
            'code' => $this->fromCode($data['discount_code'] ?? null, $order),
            default => [null, null, null],
        };

        return [
            'discount_type' => $type,
            'discount_value' => $value,
            'discount_code' => $code,
            'discount_amount' => $type?->amountOff($value, $subtotal) ?? 0,
        ];
    }

    /** @return array{DiscountType, int, string} */
    private function fromCode(?string $code, ?TakeawayOrder $order = null): array
    {
        // Mã đã áp dụng cho đơn vẫn giữ, kể cả khi sau đó mã bị tắt hoặc đổi mức giảm.
        if ($order?->discount_code && strtoupper(trim((string) $code)) === $order->discount_code) {
            return [$order->discount_type, $order->discount_value, $order->discount_code];
        }

        $discount = DiscountCode::usable($code)
            ?? throw ValidationException::withMessages(['discount_code' => __('takeaway.code_invalid')]);

        return [$discount->type, $discount->value, $discount->code];
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
