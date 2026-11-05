<?php

namespace App\Modules\Takeaway;

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
                'number' => (int) TakeawayOrder::max('number') + 1,
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
    private function discount(array $data, int $subtotal): array
    {
        [$type, $value, $code] = match ($data['discount_mode'] ?? 'none') {
            'percent' => [DiscountType::Percent, (int) $data['discount_percent'], null],
            'amount' => [DiscountType::Amount, (int) $data['discount_amount'], null],
            'code' => $this->fromCode($data['discount_code'] ?? null),
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
    private function fromCode(?string $code): array
    {
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
