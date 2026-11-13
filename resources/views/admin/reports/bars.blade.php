{{-- Thanh ngang xếp hạng: tên, thanh, số. --}}
@php($max = max(1, ...array_map(fn ($item) => $item['value'], $block['items'] ?: [['value' => 0]])))
<section @class(['panel stack report-chart', 'is-wide' => $block['wide'] ?? false])>
    <div>
        <h2>{{ $block['title'] }}</h2>
        @isset($block['note'])<small class="row-note">{{ $block['note'] }}</small>@endisset
    </div>
    @if ($block['items'] === [])
        <p class="row-note">{{ $block['empty'] ?? __('reports.no_data') }}</p>
    @else
        <ul class="bar-chart">
            @foreach ($block['items'] as $item)
                <li>
                    <span class="bar-label">{{ $item['label'] }}</span>
                    <span class="bar-track"><span class="bar-fill" style="--w: {{ round(max(1, $item['value'] / $max * 100), 2) }}%"></span></span>
                    <span class="bar-value">{{ $item['text'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($block['cut'] ?? null)
        <p class="report-cut">{{ $block['cut'] }} <a href="{{ request()->fullUrlWithQuery(['all' => 1]) }}">{{ __('reports.show_all') }}</a></p>
    @elseif (request()->boolean('all') && array_key_exists('cut', $block))
        <p class="report-cut"><a href="{{ request()->fullUrlWithQuery(['all' => null]) }}">{{ __('reports.show_less') }}</a></p>
    @endif
</section>
