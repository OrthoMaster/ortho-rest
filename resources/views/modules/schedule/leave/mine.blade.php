@extends('layouts.admin')

@section('title', __('leave.nav'))

@section('content')
    <div class="page-head">
        <h1>{{ __('leave.nav') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.schedule.leave.create') }}">{{ __('leave.add') }}</a>
    </div>

    @include('modules.schedule.leave.subnav')

    {{--
        Máy tính: cột trái hẹp (chọn người, phép còn lại); cột phải rộng: đơn đang chờ (chỉ khi có) ở trên,
        dưới là Lịch sử phép / Các đơn đã xin, mỗi trang 15 dòng. Điện thoại xếp một cột.
    --}}
    <div class="layout-side">
        <aside class="stack">
            @if ($approver && $users->isNotEmpty())
                <form method="get" class="leave-person-pick">
                    @include('partials.select-search', ['name' => 'user', 'label' => __('leave.book_of'), 'value' => $person?->id, 'options' => $users->pluck('name', 'id')->all(), 'submit' => true, 'required' => false])
                </form>
            @endif

            @if ($person)
                <section class="panel stack leave-balance-panel">
                    @include('modules.schedule.leave.balance', ['balance' => $balance])
                    @if ($approver && $balance)
                        <div><button class="btn btn-quiet btn-sm" type="button" data-open-dialog="leave-adjust">{{ __('leave.adjust') }}</button></div>
                    @endif
                </section>
            @endif
        </aside>

        <div class="stack">
            @if ($person === null)
                <p class="empty">{{ __('leave.book_none') }}</p>
            @else
                @if ($pending->isNotEmpty())
                    <section class="stack">
                        <h2>{{ __('leave.mine_pending') }} <small class="count">{{ $pending->count() }}</small></h2>
                        <section class="panel">
                            <ul class="rows leave-list">
                                @foreach ($pending as $leave)
                                    @include('modules.schedule.leave.row', ['leave' => $leave, 'approver' => false])
                                @endforeach
                            </ul>
                        </section>
                    </section>
                @endif

                <section class="stack">
                    {{-- Chuyển danh sách bên trái, lọc khoảng ngày bên phải (chỉ khi có dữ liệu). --}}
                    <div class="leave-list-bar">
                    <nav class="chips" aria-label="{{ __('leave.mine') }}">
                        <a href="{{ request()->fullUrlWithQuery(['view' => null, 'page' => null]) }}" @class(['chip', 'is-active' => $view === 'history'])>{{ __('leave.history') }}</a>
                        <a href="{{ request()->fullUrlWithQuery(['view' => 'requests', 'page' => null]) }}" @class(['chip', 'is-active' => $view === 'requests'])>{{ __('leave.mine_history') }}</a>
                    </nav>

                    @if ($hasData)
                        <form method="get" class="leave-range" onchange="this.submit()">
                            @include('partials.query-hidden', ['except' => ['from', 'to', 'page']])
                            @include('partials.date-range-picker', ['label' => __('leave.filter_range'), 'from' => $from, 'to' => $to, 'maxDays' => 3660, 'shortcuts' => false, 'placeholder' => __('leave.filter_any')])
                        </form>
                    @endif
                    </div>

                    @if ($view === 'history')
                        {{-- Lịch sử phép: cộng tự động mỗi tháng, nghỉ có lương, chỉnh tay (ai chỉnh, từ bao nhiêu thành bao nhiêu, lý do). --}}
                        @if (! $balance)
                            <p class="empty">{{ __('leave.balance_none') }}</p>
                        @elseif ($list->isEmpty())
                            <p class="empty">{{ $from ? __('leave.filter_empty') : __('leave.history_empty') }}</p>
                        @else
                            <section class="panel">
                                <ul class="rows leave-history">
                                    @foreach ($list as $entry)
                                        <li class="is-{{ $entry['kind'] }}">
                                            <span class="row-name">
                                                <strong>{{ $entry['text'] }}</strong>
                                                <small>{{ $entry['kind'] === 'monthly' ? $entry['at']->format('d/m/Y') : $entry['at']->format('d/m/Y H:i') }}@if ($entry['note']) · {{ $entry['note'] }}@endif</small>
                                            </span>
                                            <span class="leave-history-change">
                                                <b @class(['is-plus' => $entry['days'] > 0, 'is-minus' => $entry['days'] < 0])>{{ $entry['days'] > 0 ? '+' : '' }}{{ \App\Modules\Schedule\LeaveBook::format($entry['days']) }}</b>
                                                <small>{{ __('leave.history_after', ['days' => \App\Modules\Schedule\LeaveBook::format($entry['after'])]) }}</small>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    @elseif ($list->isEmpty())
                        <p class="empty">{{ $from ? __('leave.filter_empty') : __('leave.empty') }}</p>
                    @else
                        <section class="panel">
                            <ul class="rows leave-list">
                                @foreach ($list as $leave)
                                    @include('modules.schedule.leave.row', ['leave' => $leave, 'approver' => false])
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($list)
                        @include('partials.pagination', ['paginator' => $list])
                    @endif
                </section>
            @endif
        </div>
    </div>

    @if ($approver && $balance)
        <dialog id="leave-adjust" @if ($errors->hasBag('adjust')) data-auto-open @endif>
            <form method="post" action="{{ route('tenant.admin.schedule.leave.adjust', $person->id) }}" class="stack" data-guard>
                @csrf
                <h2>{{ __('leave.adjust_title', ['name' => $person->name]) }}</h2>
                <p class="row-note">{{ __('leave.adjust_lead') }}</p>
                @include('modules.schedule.leave.balance', ['balance' => $balance])
                @include('partials.number-stepper', ['name' => 'left', 'label' => __('leave.adjust_left'), 'value' => $balance['left'], 'min' => -365, 'max' => 365, 'step' => 0.25, 'suffix' => __('leave.days_unit'), 'required' => true])
                @error('left', 'adjust')<small class="error">{{ $message }}</small>@enderror
                @include('partials.textarea', ['name' => 'reason', 'label' => __('leave.adjust_reason'), 'rows' => 2, 'maxlength' => 300, 'required' => true])
                @error('reason', 'adjust')<small class="error">{{ $message }}</small>@enderror
                <div class="dialog-foot">
                    <button class="btn" type="submit">{{ __('leave.adjust_save') }}</button>
                    <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
