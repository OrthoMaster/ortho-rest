<?php

namespace App\Modules\Reservation\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Modules\Reservation\ReservationStatus;
use App\Support\CustomerRetention;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $customers = $this->customers()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $soon = CustomerRetention::dueBefore(tenant(), CarbonImmutable::now()->addDays(CustomerRetention::NOTICE_DAYS))->count();

        return view('modules.reservation.customers', [
            'customers' => $customers,
            'search' => $search,
            'soon' => $soon,
            'restaurant' => tenant(),
        ]);
    }

    /** Danh sách khách tại lúc bấm, tải về ngay; hệ thống không giữ lại bản nào. */
    public function export(): BinaryFileResponse
    {
        $restaurant = tenant();
        $rows = $this->customers()->orderBy('name')->lazy()->map(fn (Customer $customer) => [
            $customer->name,
            (string) $customer->phone,
            (string) $customer->email,
            (int) $customer->reservations_count,
            (int) $customer->cancelled_count,
            (int) $customer->no_show_count,
            CarbonImmutable::parse($customer->last_seen_at)->format('d/m/Y'),
            CustomerRetention::deleteDate($restaurant, $customer->last_seen_at)->format('d/m/Y'),
        ]);

        return SimpleXlsx::download(__('customers.export_file').'-'.now()->format('Y-m-d'), [
            __('reservation.customer_name'),
            __('reservation.customer_phone'),
            __('reservation.customer_email'),
            __('reservation.count_booked'),
            __('reservation.count_cancelled'),
            __('reservation.count_no_show'),
            __('customers.last_seen'),
            __('customers.delete_date'),
        ], $rows);
    }

    public function anonymize(Customer $customer): RedirectResponse
    {
        abort_if($customer->anonymized_at !== null, 404);

        CustomerRetention::anonymize($customer);
        ActivityLog::record(tenant()->getKey(), 'customers', __('customers.anonymized_log'));

        return back()->with('status', __('customers.anonymized'));
    }

    private function customers(): Builder
    {
        return CustomerRetention::query()->withCount([
            'reservations',
            'reservations as cancelled_count' => fn ($query) => $query->where('status', ReservationStatus::Cancelled),
            'reservations as no_show_count' => fn ($query) => $query->where('status', ReservationStatus::NoShow),
        ]);
    }
}
