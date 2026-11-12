{{-- Ô vuông các màn hình khu vực: Đơn mang đi (luôn đầu) và từng khu kèm số món chờ. Dùng ở Màn hình khu vực và Tổng quan. --}}
<ul class="table-grid station-grid">
    @if ($prep)
        <li>
            <a href="{{ route('tenant.admin.kitchen.takeaway') }}" @class(['table-tile', 'is-takeaway', 'is-open' => $prepCount > 0])>
                <strong>{{ __('kitchen.prep') }}</strong>
                @if ($prepCount > 0)
                    <small class="badge badge-warn">{{ __('kitchen.prep_count', ['count' => $prepCount]) }}</small>
                @else
                    <small>{{ __('kitchen.prep_none') }}</small>
                @endif
            </a>
        </li>
    @endif
    @foreach ($stations as $station)
        @php($count = $pending[$station->id] ?? 0)
        <li>
            <a href="{{ route('tenant.admin.kitchen.show', $station) }}" @class(['table-tile', 'is-open' => $count > 0])>
                <strong>{{ $station->name }}</strong>
                @if ($count > 0)
                    <small class="badge badge-warn">{{ __('kitchen.pending_count', ['count' => $count]) }}</small>
                @else
                    <small>{{ __('kitchen.no_pending') }}</small>
                @endif
            </a>
        </li>
    @endforeach
</ul>
