{{-- Hộp Món đã chọn: sửa, bỏ từng món; bấm ra ngoài thì đóng. $send: chữ của nút gửi; $countAttr, $totalAttr: thuộc tính để JS điền số món, tổng; $fixed: món cố định (không sửa được). --}}
<dialog id="dine-cart" class="dine-dialog" aria-labelledby="dine-cart-title" data-backdrop-close>
    <div class="stack">
        <div class="panel-head dialog-head">
            <h2 id="dine-cart-title">{{ __('order.list') }}</h2>
            <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
        </div>
        <p class="row-note" data-cart-empty>{{ __('order.empty') }}</p>
        @includeWhen(isset($fixed), 'partials.order.fixed-lines', ['lines' => $fixed ?? collect()])
        <ul class="rows dine-lines" data-cart-lines></ul>
        <div class="picker-foot">
            <span>
                <small {{ $countAttr }}></small>
                <strong {{ $totalAttr }}></strong>
            </span>
            <button class="btn" type="button" data-cart-send>{{ $send }}</button>
        </div>
    </div>
</dialog>
