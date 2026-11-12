{{-- Lưới bàn theo khu vực. Bàn có món đã xong chưa mang ra: dấu cảnh báo và số món chờ mang ra. --}}
@foreach ($areas as $area)
    <section class="table-area">
        <h2><span class="area-code">{{ $area->code }}</span> {{ $area->name }}</h2>
        <ul class="table-grid">
            @for ($number = 1; $number <= $area->table_count; $number++)
                @php($table = $open->get($area->id.'-'.$number))
                @php($ready = $table?->lines->where('status', \App\Modules\DineIn\LineStatus::Done)->sum('quantity') ?? 0)
                @php($cooking = $table?->lines->where('status', \App\Modules\DineIn\LineStatus::New)->sum('quantity') ?? 0)
                <li>
                    <a href="{{ route('tenant.admin.dinein.tables.show', $area->tableLabel($number)) }}" @class(['table-tile', 'is-open' => $table, 'has-ready' => $ready > 0])>
                        <strong>{{ $area->tableLabel($number) }}</strong>
                    @if ($ready > 0)
                        <span class="tile-alert" title="{{ __('dinein.ready_count', ['count' => $ready]) }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        </span>
                    @endif
                        @if ($table)
                            <span>{{ vnd($table->subtotal()) }}</span>
                            @if ($ready > 0)
                                <small class="badge badge-warn">{{ __('dinein.ready_count', ['count' => $ready]) }}</small>
                            @elseif ($cooking > 0)
                                <small class="badge badge-warn">{{ __('dinein.in_progress', ['count' => $cooking]) }}</small>
                            @endif
                        @else
                            <small>{{ __('dinein.free') }}</small>
                        @endif
                    </a>
                </li>
            @endfor
        </ul>
    </section>
@endforeach
