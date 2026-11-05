@if ($paginator->hasPages())
    <nav class="pagination">
        @if ($paginator->previousPageUrl())
            <a href="{{ $paginator->previousPageUrl() }}">{{ __('pagination.previous') }}</a>
        @endif
        @if ($paginator->nextPageUrl())
            <a href="{{ $paginator->nextPageUrl() }}">{{ __('pagination.next') }}</a>
        @endif
    </nav>
@endif
