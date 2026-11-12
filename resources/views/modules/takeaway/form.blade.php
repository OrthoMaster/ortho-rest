@extends('layouts.admin')

@php($editing = $order->exists)

@section('title', $editing ? __('takeaway.edit_title', ['code' => $order->code()]) : __('takeaway.add'))

@section('content')
    @include('partials.page-title', [
        'title' => $editing ? __('takeaway.edit_title', ['code' => $order->code()]) : __('takeaway.add'),
        'back' => $editing ? route('tenant.admin.takeaway.show', $order) : back_url(route('tenant.admin.takeaway.index')),
    ])

    @if ($categories->isEmpty() && ! $editing)
        <p class="empty">{{ __('takeaway.no_menu') }}</p>
    @else
        {{--
            Máy tính: cột trái là Món đã chọn, cột phải là khách, giờ nhận, giảm giá. Điện thoại một cột.
            Bấm Chọn món mở hộp danh sách món; chọn món giống Lấy đơn tại bàn (tuỳ chọn, số lượng, ghi chú).
        --}}
        <form method="post" action="{{ $editing ? route('tenant.admin.takeaway.update', $order) : route('tenant.admin.takeaway.store') }}" class="stack order-form" data-guard data-order-form data-dine-order
              @include('partials.order.labels') data-check-url="{{ route('tenant.admin.takeaway.discounts.check') }}"
              data-fixed-count="{{ $detached->sum('quantity') }}" data-fixed-total="{{ $detached->sum(fn ($line) => $line->lineTotal()) }}"
              @if ($order->discount_code) data-kept-code="{{ $order->discount_code }}" data-kept-type="{{ $order->discount_type->value }}" data-kept-value="{{ $order->discount_value }}" data-kept-label="{{ $order->discount_type->label($order->discount_value) }}" @endif>
            @csrf
            @if ($editing) @method('put') @endif
            <script type="application/json" data-dine-menu>@json($menu)</script>
            <script type="application/json" data-dine-old>@json($lines)</script>
            <script type="application/json" data-dine-kept>@json((object) $kept)</script>
            <div data-dine-inputs></div>

            <div class="cols cols-2 takeaway-form">
            <section class="panel">
                <div class="panel-head">
                    <h2>{{ __('takeaway.picked_title') }}</h2>
                    <button class="btn btn-sm" type="button" data-open-dialog="pick-dialog">{{ __('takeaway.pick_items') }}</button>
                </div>
                @if ($errors->has('lines') || $errors->has('lines.*'))
                    <small class="error" role="alert">{{ $errors->first('lines') ?: $errors->first('lines.*') }}</small>
                @endif
                <p class="row-note" data-cart-empty>{{ __('takeaway.picked_empty') }}</p>
                @include('partials.order.fixed-lines', ['lines' => $detached])
                <ul class="rows dine-lines" data-cart-lines></ul>
            </section>

            <div class="stack">

            <section class="panel stack">
                <h2>{{ __('takeaway.customer') }}</h2>
                <div class="form-row">
                    @include('partials.field', ['name' => 'customer_name', 'label' => __('reservation.customer_name'), 'value' => $order->customer?->name])
                    @include('partials.field', ['name' => 'customer_phone', 'type' => 'tel', 'label' => __('reservation.customer_phone'), 'value' => $order->customer?->phone])
                </div>
                @include('partials.textarea', ['name' => 'notes', 'label' => __('reservation.notes'), 'rows' => 2, 'value' => $order->notes])
            </section>

            <section class="panel stack" data-pickup>
                <h2>{{ __('takeaway.pickup') }}</h2>
                <div class="pickup-grid">
                    @include('partials.date-picker', ['name' => 'pickup_date', 'label' => __('takeaway.pickup_date'), 'value' => $defaultDate, 'min' => $minDate])
                    @include('partials.time-picker', [
                        'name' => 'pickup_time', 'label' => __('takeaway.pickup_time'), 'value' => $defaultTime,
                        'opens' => $pickupOpens, 'closes' => $pickupCloses,
                        'outside' => __('takeaway.outside_pickup', ['opens' => $pickupOpens, 'closes' => $pickupCloses]),
                        'past' => __('takeaway.pickup_past'),
                    ])
                </div>
                @error('pickup_at')<small class="error">{{ $message }}</small>@enderror
            </section>

            {{-- Đơn đang có mã giảm giá vẫn giữ và sửa được dù đã ẩn lựa chọn Mã. --}}
            @php($codesOn = config('liresto.discount_codes') || $order->discount_code)
            <section class="panel" data-discount @unless ($codesOn || $order->discount_type || auth()->user()->can('takeaway.direct-discount')) hidden @endunless>
                <div class="panel-head">
                    <h2 id="discount-title">{{ __('takeaway.discount') }}</h2>
                    @php($discountOn = old('discount_enabled', $order->discount_type !== null))
                    <input type="checkbox" name="discount_enabled" value="1" aria-labelledby="discount-title" data-discount-toggle @checked($discountOn)>
                </div>
                <div class="stack" data-discount-body @unless ($discountOn) hidden @endunless>
                    @php($currentMode = $order->discount_code ? 'code' : ($order->discount_type?->value ?? ($codesOn ? 'code' : 'percent')))
                    @php($keptDirect = $order->discount_type && ! $order->discount_code)
                    @can('takeaway.direct-discount')
                        @php($mode = in_array(old('discount_mode'), ['code', 'percent', 'amount'], true) ? old('discount_mode') : $currentMode)
                        <div class="segmented" role="radiogroup" aria-label="{{ __('takeaway.discount') }}">
                            @foreach ($codesOn ? ['code', 'percent', 'amount'] : ['percent', 'amount'] as $option)
                                <label><input type="radio" name="discount_mode" value="{{ $option }}" @checked($mode === $option)> <span>{{ __('takeaway.discount_mode.'.$option) }}</span></label>
                            @endforeach
                        </div>
                    @else
                        <input type="hidden" name="discount_mode" value="{{ $currentMode }}">
                        @if ($keptDirect)
                            {{-- Không có quyền đổi mức giảm: giữ nguyên, tắt toggle thì bỏ giảm giá. --}}
                            <p>{{ __('takeaway.discount_kept', ['discount' => $order->discount_type->label($order->discount_value)]) }}</p>
                            @if ($currentMode === 'percent')
                                <input type="hidden" name="discount_percent" value="{{ $order->discount_value }}">
                            @else
                                <input type="hidden" name="discount_thousands" value="{{ intdiv($order->discount_value, 1000) }}">
                            @endif
                        @endif
                    @endcan
                    <div data-discount-field="code" hidden>
                        <div class="inline-form">
                            <input name="discount_code" value="{{ old('discount_code', $order->discount_code) }}" placeholder="{{ __('takeaway.code_example') }}" aria-label="{{ __('takeaway.code') }}" autocomplete="off" data-code-input>
                            <button class="btn btn-quiet" type="button" data-code-apply>{{ __('takeaway.code_apply') }}</button>
                        </div>
                        <small class="code-message" data-code-message></small>
                        @error('discount_code')<small class="error">{{ $message }}</small>@enderror
                    </div>
                    @can('takeaway.direct-discount')
                        <div data-discount-field="percent" hidden>
                            @include('modules.takeaway.number-stepper', ['name' => 'discount_percent', 'label' => __('takeaway.discount_percent'), 'default' => $order->discount_type?->value === 'percent' && ! $order->discount_code ? $order->discount_value : 5, 'max' => 100, 'suffix' => '%'])
                        </div>
                        <div data-discount-field="amount" hidden>
                            @include('modules.takeaway.number-stepper', ['name' => 'discount_thousands', 'label' => __('takeaway.discount_amount'), 'default' => $order->discount_type?->value === 'amount' && ! $order->discount_code ? intdiv($order->discount_value, 1000) : 10, 'max' => 999999, 'suffix' => __('takeaway.thousands_suffix')])
                        </div>
                    @endcan
                </div>
            </section>

            </div>
            </div>

            <div class="order-bar">
                <span>
                    <small data-order-count></small>
                    <small data-order-saving hidden></small>
                    <strong data-order-total></strong>
                </span>
                <button class="btn" type="submit">{{ $editing ? __('takeaway.update') : __('takeaway.save') }}</button>
            </div>

        </form>

        {{-- Hộp chọn món: danh sách món như Lấy đơn tại bàn, bấm món mở hộp tuỳ chọn. --}}
        <dialog id="pick-dialog" class="picker" aria-labelledby="pick-dialog-title">
            <div class="picker-head">
                <div class="panel-head">
                    <h2 id="pick-dialog-title">{{ __('takeaway.pick_items') }}</h2>
                    <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
                </div>
                <input type="search" data-search-input placeholder="{{ __('order.search') }}" aria-label="{{ __('order.search') }}" autocomplete="off">
            </div>
            <div class="picker-body stack">
                @include('partials.order.menu', ['price' => 'price_takeaway', 'search' => false])
            </div>
            <div class="picker-foot">
                <span>
                    <small data-order-count></small>
                    <strong data-order-total></strong>
                </span>
                <button class="btn" type="button" data-close-dialog>{{ __('takeaway.done') }}</button>
            </div>
        </dialog>

        @include('partials.order.item-dialog')
    @endif
@endsection
