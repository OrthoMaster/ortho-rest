{{--
    Các ô tặng ưu đãi lần sau, dùng ở hộp thoại Tặng ưu đãi khách quen và Ghi sự cố.
    $prefix: tiền tố id (hai hộp thoại cùng trang), $bag: túi lỗi, $customer: khách đang gắn, $reason: có ô Ghi chú.
--}}
@if ($customer)
    <div class="field">
        <label class="check"><input type="checkbox" name="for_customer" value="1" @checked(old('for_customer', $bag->any() ? null : '1'))> {{ __('dinein.gift_for_customer', ['name' => $customer->name]) }}</label>
        <small class="hint">{{ __('dinein.gift_for_customer_hint') }}</small>
    </div>
@endif
@php($giftType = old('type', 'percent'))
<div class="segmented" role="radiogroup" aria-label="{{ __('dinein.gift_type') }}">
    <label><input type="radio" name="type" value="percent" @checked($giftType === 'percent')> <span>{{ __('dinein.gift_percent') }}</span></label>
    <label><input type="radio" name="type" value="amount" @checked($giftType === 'amount')> <span>{{ __('dinein.gift_amount') }}</span></label>
</div>
<div data-gift="percent">
    @include('partials.number-stepper', ['name' => 'percent', 'id' => $prefix.'percent', 'label' => __('dinein.gift_percent_value'), 'value' => 10, 'min' => 1, 'max' => 100, 'suffix' => '%'])
</div>
<div data-gift="amount">
    @include('partials.number-stepper', ['name' => 'thousands', 'id' => $prefix.'thousands', 'label' => __('dinein.gift_amount_value'), 'value' => 20, 'min' => 1, 'max' => 999999, 'suffix' => __('takeaway.thousands_suffix')])
</div>
@if ($reason ?? true)
    <div class="field">
        <label for="{{ $prefix }}reason">{{ __('dinein.gift_reason') }}</label>
        <input id="{{ $prefix }}reason" name="reason" value="{{ old('reason') }}" maxlength="300" placeholder="{{ __('dinein.gift_reason_example') }}">
        @if ($bag->has('reason'))<small class="error">{{ $bag->first('reason') }}</small>@endif
    </div>
@endif
{{-- Mặc định 1 tháng; lịch có nút 1 tháng, 2 tháng; để trống là không hạn. --}}
@include('partials.date-picker', ['name' => 'expires_on', 'id' => $prefix.'expires_on', 'label' => __('dinein.gift_expires'),
    'value' => today()->addMonthNoOverflow()->toDateString(), 'min' => today()->toDateString(), 'months' => [1, 2],
    'placeholder' => __('dinein.gift_expires_none'), 'withYear' => true])
@if ($bag->has('expires_on'))<small class="error">{{ $bag->first('expires_on') }}</small>@endif
