<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Modules\DineIn\LineLog;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Tables;
use App\Modules\Kitchen\Kitchen;
use App\Modules\Menu\ChosenOptions;
use App\Modules\Menu\Models\MenuCategory;
use App\Modules\Menu\Models\MenuItem;
use App\Modules\Menu\Models\MenuItemOptionGroup;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\ReservationBooking;
use App\Modules\Reservation\ReservationStatus;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TableController extends Controller
{
    /** Mục Lấy đơn tại bàn ở menu trái: mở trang đầu tiên người này dùng được. */
    public function home(Request $request): RedirectResponse
    {
        $user = $request->user();

        return redirect()->route(match (true) {
            $user->can('dinein.order') => 'tenant.admin.dinein.tables.index',
            $user->can('dinein.settings') => 'tenant.admin.restaurant.capacity.index',
            default => abort(403),
        });
    }

    public function index(): View
    {
        $open = DiningTable::open()
            ->with(['lines' => fn ($query) => $query->where('status', '!=', LineStatus::Cancelled)])
            ->get()
            ->keyBy(fn (DiningTable $table) => $table->area_id.'-'.$table->table_number);

        $data = ['areas' => Tables::areas(), 'open' => $open];

        // Trang tự tải lại phần lưới bàn mỗi vài giây.
        return view(request()->hasHeader('X-Board') ? 'modules.dinein.tables-board' : 'modules.dinein.tables', $data);
    }

    /** Ô nhập nhanh số bàn: gõ A12 (không phân biệt hoa thường) rồi mở. */
    public function go(Request $request): RedirectResponse
    {
        $label = strtoupper(trim((string) $request->input('label')));

        if (! preg_match('/^[A-Z]{1,2}[0-9]{1,3}$/', $label)) {
            throw ValidationException::withMessages(['label' => __('dinein.table_invalid')]);
        }

        Tables::resolve($label, 'label');

        return redirect()->route('tenant.admin.dinein.tables.show', $label);
    }

    public function show(string $label): View
    {
        [$area, $number] = Tables::resolve($label);
        $table = $this->openTable($area, $number)?->load(['lines.orderedBy', 'opener', 'customer', 'reservation', 'offer', 'incidents.creator']);

        return view(request()->hasHeader('X-Board') && $table ? 'modules.dinein.table-board' : 'modules.dinein.table', [
            'area' => $area,
            'number' => $number,
            'label' => $area->tableLabel($number),
            'table' => $table,
            'rounds' => $table?->lines->groupBy('round') ?? collect(),
        ]);
    }

    public function create(string $label): View
    {
        [$area, $number] = Tables::resolve($label);

        $categories = MenuCategory::with(['items' => fn ($query) => $query
            ->whereNotNull('price_dine_in')
            ->where('is_visible', true)
            ->with('optionGroups.options')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty());

        return view('modules.dinein.order', [
            'area' => $area,
            'number' => $number,
            'label' => $area->tableLabel($number),
            'categories' => $categories,
            'menu' => $categories->flatMap(fn (MenuCategory $category) => $category->items)->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price_dine_in,
                'groups' => $item->optionGroups->map(fn (MenuItemOptionGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'required' => $group->is_required,
                    'multiple' => $group->is_multiple,
                    'options' => $group->options->map->only(['id', 'name', 'price'])->values(),
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request, string $label): RedirectResponse
    {
        [$area, $number] = Tables::resolve($label);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'lines.*.options' => ['array'],
            'lines.*.options.*' => ['integer'],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
        ], ['lines.required' => __('dinein.order_empty')]);

        $lines = $this->buildLines($data['lines']);

        $count = DB::transaction(function () use ($request, $area, $number, $lines) {
            // Khoá các lượt của bàn này để hai người gọi cùng lúc không mở hai lượt.
            $table = DiningTable::open()->where('area_id', $area->id)->where('table_number', $number)->lockForUpdate()->first()
                ?? DiningTable::create([
                    'area_id' => $area->id,
                    'table_number' => $number,
                    'label' => $area->tableLabel($number),
                    'opened_by' => $request->user()->id,
                ]);

            $round = $table->nextRound();
            $created = new EloquentCollection;

            foreach ($lines as $line) {
                // Món không qua màn hình khu (chưa chọn khu, hoặc tắt Màn hình khu) coi như đã mang ra ngay.
                $direct = $line['dinein_station_id'] === null;
                $created->push($table->lines()->create([
                    ...$line,
                    'round' => $round,
                    'status' => $direct ? LineStatus::Served : LineStatus::New,
                    'ordered_by' => $request->user()->id,
                    'done_at' => $direct ? now() : null,
                    'served_at' => $direct ? now() : null,
                    'served_by' => $direct ? $request->user()->id : null,
                ]));
            }

            LineLog::ordered($table, $round, $created->load('station'));

            return array_sum(array_column($lines, 'quantity'));
        });

        return redirect()->route('tenant.admin.dinein.tables.show', $area->tableLabel($number))
            ->with('status', __('dinein.order_sent', ['count' => $count]));
    }

    public function serve(OrderLine $line): RedirectResponse
    {
        abort_unless($line->status === LineStatus::Done, 422);

        $line->update(['status' => LineStatus::Served, 'served_at' => now(), 'served_by' => auth()->id()]);
        LineLog::served($line);

        return back()->with('status', __('dinein.served', ['name' => $line->name]));
    }

    public function cancel(Request $request, OrderLine $line): RedirectResponse
    {
        abort_unless($line->isCancellable(), 422);
        // Khu đã làm xong (hoặc món không qua khu, đã mang ra luôn) thì cần quyền riêng mới huỷ.
        abort_if($line->status !== LineStatus::New && ! $request->user()->can('dinein.cancel'), 403);

        $before = $line->status;
        $line->update(['status' => LineStatus::Cancelled, 'cancelled_by' => $request->user()->id, 'cancelled_at' => now()]);
        LineLog::cancelled($line, $before);

        return back()->with('status', __('dinein.cancelled', ['name' => $line->name]));
    }

    public function close(Request $request, DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen(), 404);

        if ($table->hasPendingLines()) {
            return back()->with('error', __('dinein.close_pending'));
        }

        // Giảm giá tính theo tạm tính lúc đóng bàn và lưu lại.
        $discount = $table->discount();
        $table->update(['closed_at' => now(), 'closed_by' => $request->user()->id, 'discount_amount' => $discount]);
        LineLog::closed($table->load('customer'), $table->subtotal(), $discount, (int) $table->lines()->where('status', LineStatus::Served)->sum('quantity'));

        // Khách đặt trước về rồi thì đặt bàn chuyển sang Hoàn tất luôn.
        if ($table->reservation?->status === ReservationStatus::Seated) {
            app(ReservationBooking::class)->changeStatus($table->reservation, ReservationStatus::Completed, $request->user());
        }

        return redirect()->route('tenant.admin.dinein.tables.index')->with('status', __('dinein.closed', ['table' => $table->label]));
    }

    private function openTable(Area $area, int $number): ?DiningTable
    {
        return DiningTable::open()->where('area_id', $area->id)->where('table_number', $number)->first();
    }

    /**
     * Kiểm tra món và tuỳ chọn, chép tên và giá lúc gọi.
     *
     * @param  list<array{item: int, quantity: int, options?: list<int>, note?: ?string}>  $input
     * @return list<array<string, mixed>>
     */
    private function buildLines(array $input): array
    {
        $items = MenuItem::with('optionGroups.options')
            ->whereKey(array_column($input, 'item'))
            ->get()
            ->keyBy('id');
        $lines = [];

        foreach (array_values($input) as $index => $row) {
            $item = $items->get((int) $row['item']);
            $fail = fn (string $message) => throw ValidationException::withMessages(["lines.$index" => $message]);

            if ($item === null || $item->price_dine_in === null || ! $item->is_visible) {
                $fail(__('dinein.item_unavailable'));
            }

            if ($item->is_sold_out) {
                $fail(__('dinein.item_sold_out', ['name' => $item->name]));
            }

            $picked = array_map('intval', $row['options'] ?? []);
            $chosen = ChosenOptions::resolve($item, $picked, $fail, 'dinein');

            $note = trim((string) ($row['note'] ?? ''));

            $lines[] = [
                'menu_item_id' => $item->id,
                'dinein_station_id' => Kitchen::stationFor($item),
                'name' => $item->name,
                'unit_price' => $item->price_dine_in + array_sum(array_column($chosen, 'price')),
                'quantity' => (int) $row['quantity'],
                'options' => $chosen ?: null,
                'note' => $note === '' ? null : $note,
            ];
        }

        return $lines;
    }
}
