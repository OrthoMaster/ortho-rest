@extends('layouts.admin')

@section('title', __('dinein.order_title', ['table' => $label]))

@section('content')
    @include('partials.page-title', ['title' => __('dinein.order_title', ['table' => $label]), 'back' => route('tenant.admin.dinein.tables.show', $label)])

    @if ($categories->isEmpty())
        <p class="empty">{{ __('dinein.no_menu') }}</p>
    @else
        @if ($errors->any())
            <p class="flash flash-error" role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="post" action="{{ route('tenant.admin.dinein.tables.store', $label) }}" class="stack dine-order" data-guard data-dine-order
              @include('partials.order.labels')>
            @csrf
            <script type="application/json" data-dine-menu>@json($menu)</script>
            <script type="application/json" data-dine-old>@json(array_values(old('lines', [])))</script>
            <div data-dine-inputs></div>

            @include('partials.order.menu', ['price' => 'price_dine_in'])

            <div class="order-bar">
                <span>
                    <small data-dine-count></small>
                    <strong data-dine-total></strong>
                </span>
                <span class="order-bar-actions">
                    <button class="btn btn-quiet" type="button" data-open-dialog="dine-cart" data-dine-review>{{ __('dinein.order_list') }}</button>
                    <button class="btn" type="submit" data-dine-submit>{{ __('dinein.order_send') }}</button>
                </span>
            </div>
        </form>

        @include('partials.order.item-dialog')
        @include('partials.order.cart-dialog', ['send' => __('dinein.order_send'), 'countAttr' => 'data-dine-count', 'totalAttr' => 'data-dine-total'])
    @endif
@endsection
