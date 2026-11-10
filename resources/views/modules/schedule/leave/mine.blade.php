@extends('layouts.admin')

@section('title', __('leave.mine'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.schedule.leave.create') }}">{{ __('leave.add') }}</a>
    </div>

    @include('modules.schedule.subnav')

    {{-- Phần của riêng mình: phép còn lại, đơn đang chờ và các đơn đã xin. --}}
    <div class="stack w-md">
        <section class="panel">
            @include('modules.schedule.leave.balance', ['balance' => $balance])
        </section>

        @php($pendingLeaves = $leaves->where('status', \App\Modules\Schedule\LeaveStatus::Pending))
        @php($pastLeaves = $leaves->where('status', '!=', \App\Modules\Schedule\LeaveStatus::Pending))

        <section class="stack">
            <h2>{{ __('leave.mine_pending') }}</h2>
            @if ($pendingLeaves->isEmpty())
                <p class="empty">{{ __('leave.mine_pending_none') }}</p>
            @else
                <section class="panel">
                    <ul class="rows leave-list">
                        @foreach ($pendingLeaves as $leave)
                            @include('modules.schedule.leave.row', ['leave' => $leave, 'approver' => false])
                        @endforeach
                    </ul>
                </section>
            @endif
        </section>

        @if ($pastLeaves->isNotEmpty())
            <section class="stack">
                <h2>{{ __('leave.mine_history') }}</h2>
                <section class="panel">
                    <ul class="rows leave-list">
                        @foreach ($pastLeaves as $leave)
                            @include('modules.schedule.leave.row', ['leave' => $leave, 'approver' => false])
                        @endforeach
                    </ul>
                </section>
            </section>
        @endif
    </div>
@endsection
