<nav class="subnav">
    @foreach ($links as $link)
        <a href="{{ $link['url'] }}" @class(['active' => $link['active']])>{{ $link['label'] }}@if (($link['count'] ?? 0) > 0)<span class="subnav-count">{{ $link['count'] }}</span>@endif</a>
    @endforeach
</nav>
