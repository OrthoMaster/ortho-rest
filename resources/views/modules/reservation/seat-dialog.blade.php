{{--
    Khách đặt trước đến: hỏi lại và cho chọn bàn luôn (khi quán bật Lấy đơn tại bàn).
    Nút data-seat của từng đặt bàn mở hộp thoại này và đặt địa chỉ gửi.
--}}
@if (\App\Modules\DineIn\Tables::canSeat(auth()->user()))
    <dialog class="confirm-dialog seat-dialog" id="seat-dialog" aria-labelledby="seat-title">
        <form method="post" action="" class="stack" data-seat-form>
            @csrf
            <input type="hidden" name="status" value="{{ \App\Modules\Reservation\ReservationStatus::Seated->value }}">
            <h2 id="seat-title">{{ __('reservation.seat_title') }}</h2>
            <p data-seat-summary></p>
            @include('partials.select-search', ['name' => 'table', 'label' => __('reservation.seat_table'), 'required' => false,
                'options' => ['' => __('reservation.seat_none')] + \App\Modules\DineIn\Tables::seatOptions(), 'value' => '',
                'hint' => __('reservation.seat_hint')])
            <div class="dialog-foot">
                <button class="btn" type="submit">{{ __('reservation.seat_title') }}</button>
                <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
            </div>
        </form>
    </dialog>
@endif
