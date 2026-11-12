{{--
    Hộp chọn một món, dùng cả để sửa món đã chọn. Thứ tự: số lượng, Tuỳ chọn (đóng mở được,
    vì có món nhiều tuỳ chọn), ghi chú. Đầu và nút Thêm vào đơn đứng yên, phần giữa cuộn dọc.
--}}
<dialog id="dine-item" class="dine-dialog item-dialog" aria-labelledby="dine-item-name">
    <form method="dialog" class="item-form" data-dine-item-form>
        <div class="panel-head item-head">
            <div>
                <h2 id="dine-item-name" data-item-name></h2>
                <small class="row-note" data-item-price></small>
            </div>
            <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
        </div>
        <div class="item-body stack">
            @include('partials.number-stepper', ['name' => 'item_quantity', 'label' => __('order.quantity'), 'value' => 1, 'min' => 1, 'max' => 99])
            <details class="item-options" data-item-options>
                <summary>
                    <span>{{ __('order.options') }}<small data-item-picked></small></span>
                </summary>
                <div class="stack" data-item-groups></div>
            </details>
            <div class="field">
                <label for="item-note">{{ __('order.note') }}</label>
                <textarea id="item-note" rows="2" maxlength="200" placeholder="{{ __('order.note_example') }}" data-item-note></textarea>
            </div>
        </div>
        <div class="item-foot">
            <small class="error" data-item-error hidden></small>
            <button class="btn" type="submit" data-item-add></button>
        </div>
    </form>
</dialog>
