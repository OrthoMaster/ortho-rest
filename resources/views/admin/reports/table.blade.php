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
</section>
