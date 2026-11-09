@php($links = [])
@can('reservation.view')
    @php($links[] = ['url' => route('tenant.admin.reservation.index'), 'label' => __('reservation.today'), 'active' => request()->routeIs('tenant.admin.reservation.index')])
@endcan
@can('reservation.manage')
    {{-- Số đặt bàn sắp tới đang chờ xác nhận, để người quản lý thấy ngay. --}}
    @php($pending = \App\Modules\Reservation\Models\Reservation::where('status', \App\Modules\Reservation\ReservationStatus::Pending)->where('reserved_at', '>=', today())->count())
    @php($links[] = ['url' => route('tenant.admin.reservation.manage'), 'label' => __('reservation.manage'), 'count' => $pending, 'active' => request()->routeIs('tenant.admin.reservation.manage')])
@endcan
@can('reservation.settings')
    @php($links[] = ['url' => route('tenant.admin.reservation.settings.edit'), 'label' => __('reservation.settings'), 'active' => request()->routeIs('tenant.admin.reservation.settings.*')])
@endcan
@include('partials.subnav', ['links' => $links])
