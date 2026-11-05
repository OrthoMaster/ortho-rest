<nav class="subnav">
    @foreach ($links as $link)
        <a href="{{ $link['url'] }}" @class(['active' => $link['active']])>{{ $link['label'] }}</a>
    @endforeach
</nav>
