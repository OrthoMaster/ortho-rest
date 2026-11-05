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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ReservationController extends Controller
{
    private const ALL_PER_PAGE = 50;

    public function __construct(private ReservationBooking $booking) {}

    public function index(Request $request): View
    {
        $status = ReservationStatus::tryFrom((string) $request->query('status'));
        $areaId = $request->integer('area') ?: null;

        $query = Reservation::with(['customer', 'area'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($areaId, fn ($query) => $query->where('area_id', $areaId));

        $filters = [
            'status' => $status,
            'areaId' => $areaId,
            'areas' => Area::orderBy('name')->get(),
        ];

        if ($request->boolean('all')) {
            return view('modules.reservation.index', [...$filters, 'showAll' => true, 'reservations' => $this->allDays($query)]);
        }

        try {
            $date = CarbonImmutable::parse((string) $request->query('date', 'today'))->startOfDay();
        } catch (Throwable) {
            $date = CarbonImmutable::today();
        }

        $reservations = $query->whereBetween('reserved_at', [$date, $date->endOfDay()])->orderBy('reserved_at')->get();

        return view('modules.reservation.index', [
            ...$filters,
            'showAll' => false,
            'date' => $date,
            'reservations' => $reservations,
            'guests' => $reservations->filter(fn ($reservation) => $reservation->status->holdsSeats())->sum('party_size'),
        ]);
    }

    /** Hôm nay và sắp tới lên đầu theo thứ tự thời gian, các ngày đã qua xuống sau, mới nhất trước. */
    private function allDays(Builder $query): LengthAwarePaginator
    {
        $today = today();

        return $query
            ->orderByRaw('reserved_at < ?', [$today])
            ->orderByRaw('CASE WHEN reserved_at >= ? THEN reserved_at END ASC', [$today])
            ->orderByDesc('reserved_at')
            ->paginate(self::ALL_PER_PAGE)
            ->withQueryString();
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
        $reservation->load(['customer', 'area', 'creator', 'statusLogs.user']);

        return view('modules.reservation.show', compact('reservation'));
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
        $this->booking->update($reservation, $this->validated($request));

        return redirect()->route('tenant.admin.reservation.show', $reservation)->with('status', __('reservation.saved'));
    }

    public function changeStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::enum(ReservationStatus::class)]])['status'];

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
        return Area::where('is_active', true)->orderBy('name')->get();
    }
}
