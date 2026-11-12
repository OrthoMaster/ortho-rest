{{-- Hộp chọn một món: tuỳ chọn theo nhóm (chip), số lượng, ghi chú. Dùng cả để sửa món đã chọn. --}}
<dialog id="dine-item" class="dine-dialog" aria-labelledby="dine-item-name">
    <form method="dialog" class="stack" data-dine-item-form>
        <div class="panel-head">
            <div>
                <h2 id="dine-item-name" data-item-name></h2>
                <small class="row-note" data-item-price></small>
            </div>
            <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
        </div>
        <div class="stack" data-item-groups></div>
        @include('partials.number-stepper', ['name' => 'item_quantity', 'label' => __('order.quantity'), 'value' => 1, 'min' => 1, 'max' => 99])
        <div class="field">
            <label for="item-note">{{ __('order.note') }}</label>
            <textarea id="item-note" rows="2" maxlength="200" placeholder="{{ __('order.note_example') }}" data-item-note></textarea>
        </div>
        <small class="error" data-item-error hidden></small>
        <button class="btn" type="submit" data-item-add></button>
    </form>
</dialog>
