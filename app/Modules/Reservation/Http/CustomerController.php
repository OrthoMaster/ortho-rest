<?php

namespace App\Modules\Reservation\Http;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Reservation\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->withCount([
                'reservations',
                'reservations as cancelled_count' => fn ($query) => $query->where('status', ReservationStatus::Cancelled),
                'reservations as no_show_count' => fn ($query) => $query->where('status', ReservationStatus::NoShow),
            ])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('modules.reservation.customers', compact('customers', 'search'));
    }
}
