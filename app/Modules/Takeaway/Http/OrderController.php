<?php

namespace App\Modules\Takeaway\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuItemOptionGroup;
use App\Modules\ModuleManager;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\Models\TakeawayOrderItem;
use App\Modules\Takeaway\OrderStatus;
use App\Modules\Takeaway\PlaceOrder;
use App\Support\BillNumber;
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

        $order = $this->orders->handle($data['lines'], $data, $request->user());

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

        $oldCode = $order->code();
        $updated = $this->orders->update($order, $data['lines'] ?? [], $data, $request->user());

        $message = $updated->code() === $oldCode ? __('takeaway.updated') : __('takeaway.updated_code', ['code' => $updated->code()]);

        return redirect()->route('tenant.admin.takeaway.show', $order)->with('status', $message);
    }

    /**
     * Form tạo / sửa đơn: chọn món giống gọi món tại bàn (bấm món mở hộp tuỳ chọn, số lượng, ghi chú).
     * Món đang có trong đơn luôn có trong dữ liệu menu để sửa được, kể cả khi menu đã ẩn nó.
     */
    private function form(TakeawayOrder $order): View
    {
        $settings = $this->modules->settings(tenant(), 'takeaway');
        $default = now()->ceilMinutes(self::MINUTE_STEP);
        $inOrder = $order->exists ? $order->items->whereNotNull('menu_item_id') : collect();
        $ids = $inOrder->pluck('menu_item_id')->unique()->values()->all();

        $categories = MenuCategory::with(['items' => fn ($query) => $query
            ->where(fn ($query) => $query->where(fn ($query) => $query->whereNotNull('price_takeaway')->where('is_visible', true))->orWhereIn('id', $ids))
            ->with('optionGroups.options')])
            ->orderBy('sort_order')
            ->get();

        $menu = $categories->flatMap(fn (MenuCategory $category) => $category->items)->unique('id')->map(fn (MenuItem $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->price_takeaway ?? 0,
            'groups' => $item->optionGroups->map(fn (MenuItemOptionGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'required' => $group->is_required,
                'multiple' => $group->is_multiple,
                'options' => $group->options->map->only(['id', 'name', 'price'])->values(),
            ])->values(),
        ])->values();

        // Danh sách để chọn: chỉ món đang bán mang đi (món hết hiện nhãn Hết món).
        $listed = $categories
            ->filter(fn (MenuCategory $category) => $category->is_visible)
            ->each(fn (MenuCategory $category) => $category->setRelation('items', $category->items->filter(fn (MenuItem $item) => $item->is_visible && $item->price_takeaway !== null)->values()))
            ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty())
            ->values();

        // Đơn đang sửa: mỗi món giữ id dòng, giá lúc đặt và tuỳ chọn đã chép để hiện đúng như trên đơn.
        $lines = $inOrder->map(fn (TakeawayOrderItem $line) => [
            'item' => $line->menu_item_id,
            'quantity' => $line->quantity,
            'options' => $line->optionIds(),
            'note' => $line->note ?? '',
            'line' => $line->id,
            'unit' => $line->unit_price,
            'name' => $line->name,
            'optionLines' => $line->optionLines(),
        ])->values()->all();

        return view('modules.takeaway.form', [
            'order' => $order,
            'categories' => $listed,
            'menu' => $menu,
            'lines' => old('lines') !== null ? array_values(old('lines')) : $lines,
            'kept' => collect($lines)->keyBy('line')->all(),
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
            'customer_phone' => ['nullable', 'string', Phone::RULE],
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => [$order ? 'nullable' : 'required', 'array', 'max:100'],
            'lines.*.item' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'lines.*.options' => ['array'],
            'lines.*.options.*' => ['integer'],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
            'lines.*.line' => ['nullable', 'integer'],
            'discount_mode' => ['nullable', Rule::in(['none', 'code', 'percent', 'amount'])],
            'discount_code' => ['nullable', 'required_if:discount_mode,code', 'string', 'max:40'],
            'discount_percent' => ['nullable', 'required_if:discount_mode,percent', 'integer', 'min:1', 'max:100'],
            'discount_thousands' => ['nullable', 'required_if:discount_mode,amount', 'integer', 'min:1', 'max:999999'],
        ], ['lines.required' => __('takeaway.no_items')]);

        $this->authorizeDiscount($request, $data, $order);

        $data['pickup_at'] = CarbonImmutable::parse($data['pickup_date'].' '.$data['pickup_time']);

        if (! ($order && $data['pickup_at']->equalTo($order->pickup_at))
            && $data['pickup_at']->lt(now()->subMinutes(self::PAST_GRACE_MINUTES))) {
            throw ValidationException::withMessages(['pickup_at' => __('takeaway.pickup_past')]);
        }

        // Số điện thoại không bắt buộc, nhưng nhập thì phải đúng.
        $data['customer_phone'] = filled($data['customer_phone'] ?? null) ? Phone::normalize($data['customer_phone']) : null;
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
        $data = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)], 'bill_number' => BillNumber::RULES]);
        $status = OrderStatus::from($data['status']);

        $order = $this->orders->changeStatus($order, $status);

        // Giao đơn: số hoá đơn của máy tính tiền (không bắt buộc) để đối soát doanh thu.
        if ($status === OrderStatus::Delivered && ($number = BillNumber::clean($data['bill_number'] ?? null))) {
            $order->update(['bill_number' => $number]);
        }

        return back()->with('status', __('takeaway.status_changed', ['code' => $order->code(), 'status' => $status->label()]));
    }

    /** Nhập hoặc sửa số hoá đơn máy tính tiền sau khi đã giao đơn. */
    public function billNumber(Request $request, TakeawayOrder $order): RedirectResponse
    {
        abort_unless($order->status === OrderStatus::Delivered, 404);

        $number = BillNumber::clean($request->validateWithBag('bill-number', ['bill_number' => BillNumber::RULES])['bill_number'] ?? null);
        $old = $order->bill_number;
        $order->update(['bill_number' => $number]);
        ActivityLog::record($order->restaurant_id, 'takeaway', __('takeaway.log_bill_number', ['code' => $order->code()]), [($old ?? '—').' → '.($number ?? '—')], $order);

        return back()->with('status', __('takeaway.bill_number_saved'));
    }
}
