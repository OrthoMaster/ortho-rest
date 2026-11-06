<?php

namespace App\Modules\Reservation\Http;

use App\Http\Controllers\Controller;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\ReservationBooking;
use App\Modules\Reservation\ReservationSource;
use App\Modules\Reservation\ReservationStatus;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ReservationController extends Controller
{
    private const PER_PAGE = 50;

    /** Khoảng ngày xem tối đa một lần ở trang quản lý. */
    public const MAX_RANGE_DAYS = 92;

    public function __construct(private ReservationBooking $booking) {}

    /** Đặt bàn hôm nay đã nhận, cho người đón khách. */
    public function today(Request $request): View
    {
        $reservations = Reservation::with(['customer', 'area'])
            ->whereIn('status', ReservationStatus::accepted())
            ->whereBetween('reserved_at', [today(), today()->endOfDay()])
            ->orderBy('reserved_at')
            ->get();

        return view('modules.reservation.today', [
            'reservations' => $reservations,
            'guests' => $reservations->filter(fn ($reservation) => $reservation->status->holdsSeats())->sum('party_size'),
            'seated' => $reservations->where('status', ReservationStatus::Seated)->count(),
            'pendingToday' => $request->user()->can('reservation.manage')
                ? Reservation::where('status', ReservationStatus::Pending)->whereBetween('reserved_at', [today(), today()->endOfDay()])->count()
                : 0,
        ]);
    }

    /** Toàn bộ đặt bàn: sắp tới (mặc định) hoặc một khoảng ngày, lọc và tìm khách. */
    public function manage(Request $request): View
    {
        $areas = Area::orderBy('sort_order')->orderBy('id')->get();
        $statuses = filter_values('status', array_column(ReservationStatus::cases(), 'value'));
        $areaIds = filter_values('area', $areas->pluck('id')->all());
        $sources = filter_values('source', array_column(ReservationSource::cases(), 'value'));
        $search = trim((string) $request->query('q'));
        $upcoming = ! $request->filled('from') && $request->query('upcoming') !== '0';
        [$from, $to] = $upcoming ? [today()->toImmutable(), null] : $this->range($request);

        $reservations = Reservation::with(['customer', 'area'])
            ->when($upcoming, fn ($query) => $query->where('reserved_at', '>=', $from))
            ->unless($upcoming, fn ($query) => $query->whereBetween('reserved_at', [$from, $to->endOfDay()]))
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
            ->when($areaIds, fn ($query) => $query->whereIn('area_id', $areaIds))
            ->when($sources, fn ($query) => $query->whereIn('source', $sources))
            ->when($search !== '', fn ($query) => $query->whereHas('customer', function (Builder $query) use ($search): void {
                $digits = preg_replace('/\D/', '', $search);

                $query->where('name', 'like', '%'.$search.'%')
                    ->when(strlen($digits) >= 3, fn ($query) => $query->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%'));
            }))
            ->orderBy('reserved_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('modules.reservation.manage', [
            'reservations' => $reservations,
            'areas' => $areas,
            'statuses' => $statuses,
            'areaIds' => $areaIds,
            'sources' => $sources,
            'search' => $search,
            'upcoming' => $upcoming,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} mặc định hôm nay, dài quá thì cắt bớt */
    private function range(Request $request): array
    {
        $parse = function (string $key) use ($request): ?CarbonImmutable {
            try {
                return $request->filled($key) ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query($key)) : null;
            } catch (Throwable) {
                return null;
            }
        };

        $from = $parse('from') ?? CarbonImmutable::today();
        $to = $parse('to') ?? $from;

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            $to = $from->addDays(self::MAX_RANGE_DAYS - 1);
        }

        return [$from, $to];
    }

    public function create(): View
    {
        return view('modules.reservation.form', [
            'reservation' => new Reservation(['reserved_at' => now()->addHour()->startOfHour(), 'party_size' => 2]),
            'areas' => $this->activeAreas(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['source'] = $request->validate(['source' => ['required', Rule::in([ReservationSource::Phone->value, ReservationSource::WalkIn->value])]])['source'];

        $reservation = $this->booking->create($data, ReservationSource::from($data['source']), $request->user());

        return redirect()->route('tenant.admin.reservation.show', $reservation)->with('status', __('reservation.saved'));
    }

    public function show(Reservation $reservation): View
    {
        $reservation->load(['customer', 'area', 'creator', 'statusLogs.user', 'edits.user']);

        // Dòng thời gian: tạo, đổi trạng thái, sửa, theo thứ tự xảy ra.
        $history = $reservation->statusLogs->map(fn ($log) => [
            'at' => $log->created_at,
            'user' => $log->user,
            'title' => $log->from_status === null
                ? __('reservation.history_created', ['source' => $reservation->source->label(), 'status' => $log->to_status->label()])
                : __('reservation.history_status', ['from' => $log->from_status->label(), 'to' => $log->to_status->label()]),
            'lines' => [],
        ])->concat($reservation->edits->map(fn ($edit) => [
            'at' => $edit->created_at,
            'user' => $edit->user,
            'title' => __('reservation.history_edited'),
            'lines' => $edit->changes,
        ]))->sortBy(fn ($entry) => $entry['at']->getTimestamp())->values();

        return view('modules.reservation.show', ['reservation' => $reservation, 'history' => $history]);
    }

    public function edit(Reservation $reservation): View|RedirectResponse
    {
        if (! $reservation->status->holdsSeats()) {
            return redirect()->route('tenant.admin.reservation.show', $reservation)->with('error', __('reservation.not_editable'));
        }

        $reservation->load('customer');

        return view('modules.reservation.form', ['reservation' => $reservation, 'areas' => $this->activeAreas()]);
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->booking->update($reservation, $this->validated($request), $request->user());

        return redirect()->route('tenant.admin.reservation.show', $reservation)->with('status', __('reservation.saved'));
    }

    public function changeStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::enum(ReservationStatus::class)]])['status'];

        // Đặt bàn đang chờ: chỉ người quản lý đặt bàn được nhận, từ chối hay huỷ.
        abort_if($reservation->status === ReservationStatus::Pending && ! $request->user()->can('reservation.manage'), 403);

        $this->booking->changeStatus($reservation, ReservationStatus::from($status), $request->user());

        return back()->with('status', __('reservation.status_changed', ['status' => ReservationStatus::from($status)->label()]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', Phone::RULE],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('restaurant_id', tenant()->getKey())],
            'reserved_at' => ['required', 'date'],
            'party_size' => ['required', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['customer_phone'] = Phone::normalize($data['customer_phone']);
        $data['area_id'] = (int) $data['area_id'];

        return $data;
    }

    private function activeAreas()
    {
        return Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }
}
