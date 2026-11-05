<?php

namespace App\Modules\Takeaway\Http;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\ModuleManager;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\OrderStatus;
use App\Modules\Takeaway\PlaceOrder;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    private const PICKUP_DAYS = 14;

    private const SLOT_MINUTES = 15;

    private const PREPARE_MINUTES = 30;

    private const PAST_GRACE_MINUTES = 15;

    public function __construct(private PlaceOrder $orders, private ModuleManager $modules) {}

    public function index(Request $request): View
    {
        try {
            $date = CarbonImmutable::parse((string) $request->query('date', 'today'))->startOfDay();
        } catch (Throwable) {
            $date = CarbonImmutable::today();
        }

        $status = OrderStatus::tryFrom((string) $request->query('status'));

        return view('modules.takeaway.index', [
            'date' => $date,
            'status' => $status,
            'orders' => TakeawayOrder::with(['customer', 'items'])
                ->whereBetween('pickup_at', [$date, $date->endOfDay()])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderBy('pickup_at')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $settings = $this->modules->settings(tenant(), 'takeaway');
        $times = $this->pickupTimes($settings['pickup_opens'], $settings['pickup_closes']);
        $earliest = now()->addMinutes(self::PREPARE_MINUTES)->format('H:i');
        $todayTime = collect($times)->first(fn (string $time) => $time >= $earliest);

        return view('modules.takeaway.form', [
            'categories' => MenuCategory::where('is_visible', true)
                ->with(['items' => fn ($query) => $query->whereNotNull('price_takeaway')->where('is_visible', true)])
                ->orderBy('sort_order')
                ->get()
                ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty()),
            'pickupDays' => $this->pickupDays(),
            'pickupTimes' => array_combine($times, $times),
            // Hôm nay hết khung giờ thì mặc định sang ngày mai.
            'defaultDate' => ($todayTime ? today() : today()->addDay())->toDateString(),
            'defaultTime' => $todayTime ?? $times[0],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->boolean('discount_enabled')) {
            $request->merge(['discount_mode' => 'none']);
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', Phone::RULE],
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'discount_mode' => ['nullable', Rule::in(['none', 'code', 'percent', 'amount'])],
            'discount_code' => ['nullable', 'required_if:discount_mode,code', 'string', 'max:40'],
            'discount_percent' => ['nullable', 'required_if:discount_mode,percent', 'integer', 'min:1', 'max:100'],
            'discount_thousands' => ['nullable', 'required_if:discount_mode,amount', 'integer', 'min:1', 'max:999999'],
        ]);

        abort_if(
            in_array($data['discount_mode'] ?? 'none', ['percent', 'amount'], true)
                && $request->user()->cannot('takeaway.direct-discount'),
            403,
        );

        $data['pickup_at'] = CarbonImmutable::parse($data['pickup_date'].' '.$data['pickup_time']);

        if ($data['pickup_at']->lt(now()->subMinutes(self::PAST_GRACE_MINUTES))) {
            throw ValidationException::withMessages(['pickup_at' => __('takeaway.pickup_past')]);
        }

        $data['customer_phone'] = Phone::normalize($data['customer_phone']);
        $data['discount_amount'] = ($data['discount_thousands'] ?? 0) * 1000;
        $quantities = array_map('intval', $data['items']);

        $order = $this->orders->handle($quantities, $data, $request->user());

        return redirect()->route('tenant.admin.takeaway.show', $order)->with('status', __('takeaway.saved'));
    }

    public function show(TakeawayOrder $order): View
    {
        $order->load(['customer', 'items', 'creator']);

        return view('modules.takeaway.show', compact('order'));
    }

    public function changeStatus(Request $request, TakeawayOrder $order): RedirectResponse
    {
        $status = OrderStatus::from($request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]])['status']);

        $this->orders->changeStatus($order, $status);

        return back()->with('status', __('takeaway.status_changed', ['number' => $order->number, 'status' => $status->label()]));
    }

    /** @return array<string, string> ngày => "Hôm nay, Thứ năm 05/11" */
    private function pickupDays(): array
    {
        $days = [];

        foreach (range(0, self::PICKUP_DAYS - 1) as $offset) {
            $day = today()->addDays($offset);
            $name = __('reservation.weekday.'.$day->dayOfWeekIso).' '.$day->format('d/m');

            $days[$day->toDateString()] = match ($offset) {
                0 => __('takeaway.today').', '.$name,
                1 => __('takeaway.tomorrow').', '.$name,
                default => $name,
            };
        }

        return $days;
    }

    /** @return list<string> */
    private function pickupTimes(string $opens, string $closes): array
    {
        $times = [];
        $time = CarbonImmutable::createFromFormat('H:i', $opens);
        $end = CarbonImmutable::createFromFormat('H:i', $closes);

        while ($time->lte($end)) {
            $times[] = $time->format('H:i');
            $time = $time->addMinutes(self::SLOT_MINUTES);
        }

        return $times;
    }
}
