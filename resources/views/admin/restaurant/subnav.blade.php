@php($links = [['url' => route('tenant.admin.restaurant.edit'), 'label' => __('shifts.info'), 'active' => request()->routeIs('tenant.admin.restaurant.edit')]])
@foreach (\App\Enums\ShiftType::cases() as $case)
    @php($links[] = ['url' => route('tenant.admin.restaurant.shifts.index', $case), 'label' => $case->label(), 'active' => ($type ?? null) === $case])
@endforeach
@php($links[] = ['url' => route('tenant.admin.restaurant.special-days.index'), 'label' => __('special_days.title'), 'active' => request()->routeIs('tenant.admin.restaurant.special-days.*')])
@php($links[] = ['url' => route('tenant.admin.restaurant.capacity.index'), 'label' => __('reservation.capacity'), 'active' => request()->routeIs('tenant.admin.restaurant.capacity.*', 'tenant.admin.dinein.stations.*')])
@include('partials.subnav', ['links' => $links])
