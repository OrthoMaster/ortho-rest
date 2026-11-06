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
use Illuminate\Http\JsonResponse;
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

    private const TODAY_SHIFTS_KEY = 'reservation_today_shifts';

    /** Đặt bàn hôm nay đã nhận, cho người đón khách, nhóm theo ca mở cửa. */
    public function today(Request $request): View
    {
        $periods = tenant()->periodsOn(today());
        $names = array_column($periods, 'name');

        // Ca đang lọc được nhớ trong phiên, để sau mỗi thao tác (tải lại trang) vẫn giữ.
        if ($request->boolean('shift_set')) {
            $request->session()->put(self::TODAY_SHIFTS_KEY, filter_values('shift', $names));
        }

        $chosen = array_values(array_intersect((array) $request->session()->get(self::TODAY_SHIFTS_KEY, []), $names));

        $reservations = Reservation::with(['customer', 'area'])
            ->whereIn('status', ReservationStatus::accepted())
            ->whereBetween('reserved_at', [today(), today()->endOfDay()])
            ->orderBy('reserved_at')
            ->get();

        $groups = collect($periods)->map(fn (array $period) => [
            'name' => $period['name'],
            'range' => $period['start'].' – '.$period['end'],
            'rows' => $reservations->filter(fn (Reservation $reservation) => $this->inPeriod($reservation, $period))->values(),
        ]);

        $outside = $reservations->reject(fn (Reservation $reservation) => $groups->contains(fn ($group) => $group['rows']->contains($reservation)));

        if ($chosen !== []) {
            $groups = $groups->whereIn('name', $chosen)->values();
        } elseif ($outside->isNotEmpty()) {
            $groups->push(['name' => $periods === [] ? null : __('reservation.outside_shifts'), 'range' => null, 'rows' => $outside->values()]);
        }

        $shown = $groups->flatMap(fn ($group) => $group['rows']);

        return view('modules.reservation.today', [
            'groups' => $groups,
            'periods' => $periods,
            'chosen' => $chosen,
            'count' => $shown->count(),
            'arrived' => $shown->whereIn('status', [ReservationStatus::Seated, ReservationStatus::Completed])->count(),
            'guests' => $shown->where('status', '!=', ReservationStatus::NoShow)->sum('party_size'),
            'pendingToday' => $request->user()->can('reservation.manage')
                ? Reservation::where('status', ReservationStatus::Pending)->whereBetween('reserved_at', [today(), today()->endOfDay()])->count()
                : 0,
        ]);
    }

    /** @param array{name: string, start: string, end: string} $period */
    private function inPeriod(Reservation $reservation, array $period): bool
    {
        $time = $reservation->reserved_at->format('H:i');

        return $period['end'] > $period['start']
            ? $time >= $period['start'] && $time < $period['end']
            : $time >= $period['start'] || $time < $period['end'];
    }

    /** Toàn bộ đặt bàn. Không chọn ngày: từ hôm nay trở đi. Chọn ngày: khoảng đó, quá khứ cũng được. */
    public function manage(Request $request): View
    {
        $statuses = filter_values('status', array_column(ReservationStatus::cases(), 'value'));
        $search = trim((string) $request->query('q'));
        $upcoming = ! $request->filled('from');
        [$from, $to] = $upcoming ? [today()->toImmutable(), null] : $this->range($request);

        $reservations = Reservation::with(['customer', 'area'])
            ->when($upcoming, fn ($query) => $query->where('reserved_at', '>=', $from))
            ->unless($upcoming, fn ($query) => $query->whereBetween('reserved_at', [$from, $to->endOfDay()]))
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
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
            'statuses' => $statuses,
            'search' => $search,
            'upcoming' => $upcoming,
            'from' => $upcoming ? null : $from,
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
            'reservation' => new Reservation(['reserved_at' => now()->ceilMinutes(10), 'party_size' => 2]),
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

    /** Chỗ còn theo ngày, giờ, số khách: form đặt bàn gọi sau mỗi lần đổi. */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reserved_date' => ['required', 'date_format:Y-m-d'],
            'reserved_time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:1000'],
            'ignore' => ['nullable', 'integer'],
        ]);

        $ignore = filled($data['ignore'] ?? null) ? Reservation::find($data['ignore']) : null;
        $start = CarbonImmutable::parse($data['reserved_date'].' '.$data['reserved_time']);
        $party = (int) $data['party_size'];
        $result = $this->booking->availability($start, $ignore);
        $fits = $result['left'] >= $party;

        return response()->json([
            'fits' => $fits,
            'message' => $fits
                ? __('reservation.available', ['left' => $result['left'], 'total' => $result['total']])
                : __('reservation.full', ['left' => $result['left']]),
            'warning' => $result['closed'] ? __('reservation.outside_hours') : null,
            'areas' => array_map(fn ($area) => $area + [
                'fits' => $area['left'] >= $party,
                'label' => __('reservation.seats_left', ['count' => $area['left']]),
            ], $result['areas']),
        ]);
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
            'customer_phone' => ['nullable', 'string', Phone::RULE],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('restaurant_id', tenant()->getKey())->where('is_active', true)],
            'reserved_date' => ['required', 'date_format:Y-m-d'],
            'reserved_time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['reserved_at'] = CarbonImmutable::parse($data['reserved_date'].' '.$data['reserved_time']);
        $data['customer_phone'] = filled($data['customer_phone'] ?? null) ? Phone::normalize($data['customer_phone']) : null;
        $data['area_id'] = filled($data['area_id'] ?? null) ? (int) $data['area_id'] : null;

        return $data;
    }

    private function activeAreas()
    {
        return Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }
}
