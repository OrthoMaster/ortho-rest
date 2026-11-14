{{-- Mục Nghỉ phép: Đơn xin nghỉ (người duyệt) và Ngày phép. Chỉ một tab thì không hiện. --}}
@php($links = [])
@can('schedule.leave')
    @php($links[] = ['url' => route('tenant.admin.schedule.leave.index'), 'label' => __('leave.title'), 'count' => \App\Support\Attention::for(auth()->user())['leave'], 'active' => request()->routeIs('tenant.admin.schedule.leave.index')])
@endcan
@php($links[] = ['url' => route('tenant.admin.schedule.leave.mine'), 'label' => __('leave.mine'), 'active' => request()->routeIs('tenant.admin.schedule.leave.mine')])
@if (count($links) > 1)
    @include('partials.subnav', ['links' => $links])
@endif
