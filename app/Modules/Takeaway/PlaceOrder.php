<?php

namespace App\Modules\Takeaway;

use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\ModuleManager;
use App\Modules\Takeaway\Models\TakeawayOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    public function __construct(private ModuleManager $modules) {}

    /**
     * @param  array<int, int>  $quantities  id món => số lượng
     * @param  array{customer_name: string, customer_phone: string, pickup_at: mixed, notes?: ?string, locale?: string}  $data
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

            $order = TakeawayOrder::create([
                'customer_id' => Customer::remember($data['customer_name'], $data['customer_phone'])->getKey(),
                'created_by' => $user?->getKey(),
                'number' => (int) TakeawayOrder::max('number') + 1,
                'status' => OrderStatus::New,
                'pickup_at' => $pickup,
                'total' => $items->sum(fn (MenuItem $item): int => $item->price_takeaway * $quantities[$item->getKey()]),
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
