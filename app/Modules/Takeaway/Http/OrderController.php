<?php

namespace App\Modules\Takeaway\Http;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\OrderStatus;
use App\Modules\Takeaway\PlaceOrder;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private PlaceOrder $orders) {}

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
        return view('modules.takeaway.form', [
            'categories' => MenuCategory::where('is_visible', true)
                ->with(['items' => fn ($query) => $query->whereNotNull('price_takeaway')->where('is_visible', true)])
                ->orderBy('sort_order')
                ->get()
                ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', Phone::RULE],
            'pickup_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['customer_phone'] = Phone::normalize($data['customer_phone']);
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
}
