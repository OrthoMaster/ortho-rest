<span @class(['tag', 'tag-image-only' => $tag->image_only && $tag->imageUrl()])>
    @if ($tag->imageUrl())<img src="{{ $tag->imageUrl() }}" alt="{{ $tag->image_only ? $tag->name : '' }}">@endif
    @unless ($tag->image_only && $tag->imageUrl()){{ $tag->name }}@endunless
</span>
