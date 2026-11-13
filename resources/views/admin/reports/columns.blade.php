{{--
    Biểu đồ cột theo thời gian (giờ, ngày). Một chuỗi: một màu. Hai chuỗi (tại bàn, mang đi): cột chồng, có chú thích.
    Rê chuột hoặc chạm vào cột để xem số; nhiều cột thì nhãn thưa bớt. Có bảng số đi kèm.
--}}
@php($items = $block['items'])
@php($max = max(1, ...array_map(fn ($item) => $item['value'], $items ?: [['value' => 0]])))
@php($every = max(1, (int) ceil(count($items) / 16)))
<section class="panel stack report-chart">
    <h2>{{ $block['title'] }}</h2>
    @if ($items === [] || $max <= 0 && collect($items)->sum('value') == 0)
        <p class="row-note">{{ $block['empty'] ?? __('reports.no_data') }}</p>
    @else
        @if ($block['series'] ?? null)
            <ul class="chart-legend">
                @foreach ($block['series'] as $key => $label)
                    <li><span class="chart-swatch is-{{ $loop->index + 1 }}"></span>{{ $label }}</li>
                @endforeach
            </ul>
        @endif
        <div class="col-chart" role="img" aria-label="{{ $block['title'] }}">
            @foreach ($items as $item)
                @php($height = $item['value'] > 0 ? max(1.5, $item['value'] / $max * 100) : 0)
                <div class="col" tabindex="0">
                    <div class="col-track">
                        <div class="col-bar" style="--h: {{ round($height, 2) }}%">
                            @if ($block['series'] ?? null)
                                @foreach (array_keys($block['series']) as $key)
                                    @php($part = $item['parts'][$key] ?? 0)
                                    @if ($part > 0)<span class="col-part is-{{ $loop->index + 1 }}" style="--p: {{ round($part / $item['value'] * 100, 2) }}%"></span>@endif
                                @endforeach
                            @elseif ($item['value'] > 0)
                                <span class="col-part is-1" style="--p: 100%"></span>
                            @endif
                        </div>
                    </div>
                    <span @class(['col-label', 'is-hidden' => $loop->index % $every !== 0])>{{ $item['label'] }}</span>
                    <span class="col-tip">
                        <strong>{{ $item['label'] }}</strong> {{ $item['text'] }}
                        @if ($block['series'] ?? null)
                            @foreach ($block['series'] as $key => $label)
                                <br>{{ $label }}: {{ ($block['money'] ?? false) ? vnd($item['parts'][$key] ?? 0) : $item['parts'][$key] ?? 0 }}
                            @endforeach
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
        <details class="chart-table">
            <summary>{{ __('reports.show_table') }}</summary>
            <div class="table-wrap">
                <table>
                    <tbody>
                        @foreach ($items as $item)
                            <tr><td>{{ $item['label'] }}</td><td class="num">{{ $item['text'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</section>
