{{-- Các con số chính; xu hướng so với kỳ trước ghi bằng chữ (tăng / giảm), tốt xấu thêm màu. --}}
<section class="stack">
    @isset($block['title'])<h2>{{ $block['title'] }}</h2>@endisset
    <ul class="kpi-grid">
        @foreach ($block['items'] as $item)
            <li @class(['kpi', 'is-warn' => $item['warn'] ?? false])>
                <small class="kpi-label">{{ $item['label'] }}</small>
                <strong class="kpi-value">{{ $item['value'] }}</strong>
                @if ($item['trend'] ?? null)
                    <small @class(['kpi-trend', 'is-good' => $item['trend']['good'], 'is-bad' => ! $item['trend']['good']])>{{ $item['trend']['text'] }}</small>
                @endif
                @if ($item['note'] ?? null)<small class="kpi-note">{{ $item['note'] }}</small>@endif
            </li>
        @endforeach
    </ul>
    @isset($block['note'])<small class="row-note">{{ $block['note'] }}</small>@endisset
</section>
