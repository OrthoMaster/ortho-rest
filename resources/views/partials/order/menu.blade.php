{{--
    Danh sách món để gọi (Lấy đơn tại bàn, Đặt mang đi): ô tìm, mỗi danh mục một khung,
    bấm món mở hộp chọn tuỳ chọn ($search => false khi ô tìm đặt chỗ khác). $price: tên cột giá (price_dine_in, price_takeaway).
--}}
@if ($search ?? true)
    <input type="search" data-search-input placeholder="{{ __('order.search') }}" aria-label="{{ __('order.search') }}" autocomplete="off">
@endif
<p class="row-note" data-search-empty hidden>{{ __('order.search_empty') }}</p>

@foreach ($categories as $category)
    <section class="panel dine-menu" data-search-group="{{ $category->name }}">
        <h2>{{ $category->name }}</h2>
        <ul class="rows">
            @foreach ($category->items as $item)
                <li data-search-item="{{ $item->name }}" @class(['is-sold-out' => $item->is_sold_out])>
                    @if ($item->is_sold_out)
                        <span class="row-name">{{ $item->name }}<small>{{ vnd($item->{$price}) }}</small></span>
                        <span class="badge badge-bad">{{ __('menu.sold_out') }}</span>
                    @else
                        <button class="dine-pick" type="button" data-dine-pick="{{ $item->id }}">
                            <span class="row-name">{{ $item->name }}<small>{{ vnd($item->{$price}) }}@if ($item->optionGroups->isNotEmpty()) · {{ $item->optionGroups->pluck('name')->implode(', ') }}@endif</small></span>
                            <span class="dine-picked" data-picked-count hidden></span>
                            <span class="row-btn dine-plus" aria-hidden="true">+</span>
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endforeach
