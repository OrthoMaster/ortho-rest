{{-- Bảng số: cột số canh phải; bảng rộng thì cuộn ngang trong khung. --}}
<section class="stack">
    <div>
        <h2>{{ $block['title'] }}</h2>
        @isset($block['note'])<small class="row-note">{{ $block['note'] }}</small>@endisset
    </div>
    @if ($block['rows'] === [])
        <p class="empty">{{ $block['empty'] ?? __('reports.no_data') }}</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach ($block['headers'] as $header)
                            <th @class(['num' => in_array($loop->index, $block['numeric'] ?? [], true)])>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($block['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td @class(['num' => in_array($loop->index, $block['numeric'] ?? [], true)])>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if ($block['cut'] ?? null)
        <p class="report-cut">{{ $block['cut'] }} <a href="{{ request()->fullUrlWithQuery(['all' => 1]) }}">{{ __('reports.show_all') }}</a></p>
    @elseif (request()->boolean('all') && array_key_exists('cut', $block))
        <p class="report-cut"><a href="{{ request()->fullUrlWithQuery(['all' => null]) }}">{{ __('reports.show_less') }}</a></p>
    @endif
</section>
