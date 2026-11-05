@php($links = [['url' => route('tenant.admin.restaurant.edit'), 'label' => __('shifts.info'), 'active' => request()->routeIs('tenant.admin.restaurant.edit')]])
@foreach (\App\Enums\ShiftType::cases() as $case)
    @php($links[] = ['url' => route('tenant.admin.restaurant.shifts.index', $case), 'label' => $case->label(), 'active' => ($type ?? null) === $case])
@endforeach
@include('partials.subnav', ['links' => $links])
