{{-- Phần tự tải lại của màn hình Thu ngân. --}}
<div class="cols cols-2">
    <div class="stack">
        <section class="panel stack">
            <h2>{{ __('cashier.pending') }} @if ($pending->isNotEmpty())<span class="badge badge-warn">{{ $pending->count() }}</span>@endif</h2>
            @if ($pending->isEmpty())
                <p class="row-note">{{ __('cashier.pending_empty') }}</p>
            @else
                <ul class="rows cash-list">
                    @foreach ($pending as $bill)
                        @include('modules.cashier.row', ['bill' => $bill, 'waiting' => true])
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($open->isNotEmpty())
            <section class="panel stack">
                <div>
                    <h2>{{ __('cashier.open') }}</h2>
                    <small class="row-note">{{ __('cashier.open_hint') }}</small>
                </div>
                <ul class="rows cash-list">
                    @foreach ($open as $bill)
                        @include('modules.cashier.row', ['bill' => $bill, 'waiting' => false])
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <section class="panel stack">
        <h2>{{ __('cashier.paid_today') }}</h2>
        @if ($paid->isEmpty())
            <p class="row-note">{{ __('cashier.paid_empty') }}</p>
        @else
            <ul class="rows cash-list">
                @foreach ($paid as $bill)
                    @include('modules.cashier.row', ['bill' => $bill, 'waiting' => false])
                @endforeach
            </ul>
        @endif
    </section>
</div>

@foreach ([...$pending, ...$open, ...$paid] as $bill)
    @include('modules.cashier.dialogs', ['bill' => $bill])
@endforeach
