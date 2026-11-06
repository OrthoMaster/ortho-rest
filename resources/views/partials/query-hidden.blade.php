{{-- Giữ lại tham số trên URL (kể cả bộ lọc chọn nhiều) khi gửi một form GET khác. --}}
@foreach (request()->except($except) as $queryKey => $queryValue)
    @foreach ((array) $queryValue as $queryItem)
        @if (is_scalar($queryItem))
            <input type="hidden" name="{{ is_array($queryValue) ? $queryKey.'[]' : $queryKey }}" value="{{ $queryItem }}">
        @endif
    @endforeach
@endforeach
