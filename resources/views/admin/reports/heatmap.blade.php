{{-- Thứ × giờ: ô càng đậm càng đông (một màu, nhạt → đậm); số ghi trong ô. --}}
@php($max = max(1, ...collect($block['rows'])->flatMap(fn ($row) => $row['cells'])->all() ?: [0]))
<section class="stack">
    <div>
        <h2>{{ $block['title'] }}</h2>
        @isset($block['note'])<small class="row-note">{{ $block['note'] }}</small>@endisset
    </div>
    <div class="table-wrap">
        <table class="heatmap">
            <thead>
                <tr><th></th>@foreach ($block['cols'] as $col)<th class="num">{{ $col }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($block['rows'] as $row)
                    <tr>
                        <th scope="row">{{ $row['label'] }}</th>
                        @foreach ($row['cells'] as $value)
                            @php($level = $value > 0 ? (int) round(15 + $value / $max * 80) : 0)
                            <td @class(['heat', 'is-dark' => $level > 55]) style="--level: {{ $level }}%">{{ $value ?: '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
