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
    private const MINUTE_STEP = 10;

    private const PAST_GRACE_MINUTES = 15;

    public function __construct(private PlaceOrder $orders, private ModuleManager $modules) {}

    public function index(Request $request): View
    {
        try {
            $date = CarbonImmutable::parse((string) $request->query('date', 'today'))->startOfDay();
        } catch (Throwable) {
            $date = CarbonImmutable::today();
        }

        $statuses = filter_values('status', array_column(OrderStatus::cases(), 'value'));

        return view('modules.takeaway.index', [
            'date' => $date,
            'statuses' => $statuses,
            'orders' => TakeawayOrder::with(['customer', 'items'])
                ->withCount('edits')
                ->whereBetween('pickup_at', [$date, $date->endOfDay()])
                ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
                ->orderBy('pickup_at')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new TakeawayOrder);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $quantities = array_map('intval', $data['items']);

        $order = $this->orders->handle($quantities, $data, $request->user());

        return redirect()->route('tenant.admin.takeaway.show', $order)->with('status', __('takeaway.saved'));
    }

    public function edit(TakeawayOrder $order): View|RedirectResponse
    {
        if (! $order->isEditable()) {
            return redirect()->route('tenant.admin.takeaway.show', $order)->with('error', __('takeaway.not_editable'));
        }

        return $this->form($order->load(['customer', 'items']));
    }

    public function update(Request $request, TakeawayOrder $order): RedirectResponse
    {
        $data = $this->validated($request, $order);
        $quantities = array_map('intval', $data['items']);

        $oldCode = $order->code();
        $updated = $this->orders->update($order, $quantities, $data, $request->user());

        $message = $updated->code() === $oldCode ? __('takeaway.updated') : __('takeaway.updated_code', ['code' => $updated->code()]);

        return redirect()->route('tenant.admin.takeaway.show', $order)->with('status', $message);
    }

    private function form(TakeawayOrder $order): View
    {
        $settings = $this->modules->settings(tenant(), 'takeaway');
        $default = now()->ceilMinutes(self::MINUTE_STEP);
        $inOrder = $order->exists ? $order->items->whereNotNull('menu_item_id')->keyBy('menu_item_id') : collect();
        $ids = $inOrder->keys()->all();

        // Món đang có trong đơn luôn phải hiện, kể cả khi menu đã ẩn nó.
        $categories = MenuCategory::with(['items' => fn ($query) => $query->where(fn ($query) => $query
            ->where(fn ($query) => $query->whereNotNull('price_takeaway')->where('is_visible', true))
            ->orWhereIn('id', $ids))])
            ->orderBy('sort_order')
            ->get()
            ->each(function (MenuCategory $category) use ($ids): void {
                if (! $category->is_visible) {
                    $category->setRelation('items', $category->items->whereIn('id', $ids));
                }
            })
            ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty());

        return view('modules.takeaway.form', [
            'order' => $order,
            'categories' => $categories,
            'inOrder' => $inOrder,
            'detached' => $order->exists ? $order->items->whereNull('menu_item_id') : collect(),
            'defaultDate' => $order->exists ? $order->pickup_at->toDateString() : $default->toDateString(),
            'defaultTime' => $order->exists ? $order->pickup_at->format('H:i') : $default->format('H:i'),
            'minDate' => $order->exists ? min($order->pickup_at->toDateString(), today()->toDateString()) : today()->toDateString(),
            'pickupOpens' => $settings['pickup_opens'],
            'pickupCloses' => $settings['pickup_closes'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?TakeawayOrder $order = null): array
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

        $this->authorizeDiscount($request, $data, $order);

        $data['pickup_at'] = CarbonImmutable::parse($data['pickup_date'].' '.$data['pickup_time']);

        if (! ($order && $data['pickup_at']->equalTo($order->pickup_at))
            && $data['pickup_at']->lt(now()->subMinutes(self::PAST_GRACE_MINUTES))) {
            throw ValidationException::withMessages(['pickup_at' => __('takeaway.pickup_past')]);
        }

        $data['customer_phone'] = Phone::normalize($data['customer_phone']);
        $data['discount_amount'] = ($data['discount_thousands'] ?? 0) * 1000;

        return $data;
    }

    /** Giảm trực tiếp cần quyền. Khi sửa đơn, giữ nguyên mức giảm cũ thì không cần. */
    private function authorizeDiscount(Request $request, array $data, ?TakeawayOrder $order): void
    {
        $mode = $data['discount_mode'] ?? 'none';

        if (! in_array($mode, ['percent', 'amount'], true) || $request->user()->can('takeaway.direct-discount')) {
            return;
        }

        $value = $mode === 'percent' ? (int) $data['discount_percent'] : (int) $data['discount_thousands'] * 1000;

        abort_unless($order && ! $order->discount_code && $order->discount_type?->value === $mode && $order->discount_value === $value, 403);
    }

    public function show(TakeawayOrder $order): View
    {
        $order->load(['customer', 'items', 'creator', 'edits.user']);

        return view('modules.takeaway.show', compact('order'));
    }

    public function changeStatus(Request $request, TakeawayOrder $order): RedirectResponse
    {
        $status = OrderStatus::from($request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]])['status']);

        $this->orders->changeStatus($order, $status);

        return back()->with('status', __('takeaway.status_changed', ['code' => $order->code(), 'status' => $status->label()]));
    }
}
