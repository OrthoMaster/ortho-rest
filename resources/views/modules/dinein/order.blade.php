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
              data-count-label="{{ __('dinein.order_count') }}" data-add-label="{{ __('dinein.add_to_order') }}"
              data-pick-one="{{ __('dinein.pick_one') }}" data-pick-many="{{ __('dinein.pick_many') }}" data-required-label="{{ __('dinein.required') }}"
              data-need-option="{{ __('dinein.need_option') }}" data-remove-label="{{ __('dinein.remove_line') }}"
              data-edit-label="{{ __('dinein.edit_line') }}" data-update-label="{{ __('dinein.update_line') }}">
            @csrf
            <script type="application/json" data-dine-menu>@json($menu)</script>
            <script type="application/json" data-dine-old>@json(array_values(old('lines', [])))</script>
            <div data-dine-inputs></div>

            <input type="search" data-search-input placeholder="{{ __('dinein.search') }}" aria-label="{{ __('dinein.search') }}" autocomplete="off">
            <p class="row-note" data-search-empty hidden>{{ __('dinein.search_empty') }}</p>

            @foreach ($categories as $category)
                <section class="panel dine-menu" data-search-group="{{ $category->name }}">
                    <h2>{{ $category->name }}</h2>
                    <ul class="rows">
                        @foreach ($category->items as $item)
                            <li data-search-item="{{ $item->name }}" @class(['is-sold-out' => $item->is_sold_out])>
                                @if ($item->is_sold_out)
                                    <span class="row-name">{{ $item->name }}<small>{{ vnd($item->price_dine_in) }}</small></span>
                                    <span class="badge badge-bad">{{ __('menu.sold_out') }}</span>
                                @else
                                    <button class="dine-pick" type="button" data-dine-pick="{{ $item->id }}">
                                        <span class="row-name">{{ $item->name }}<small>{{ vnd($item->price_dine_in) }}@if ($item->optionGroups->isNotEmpty()) · {{ $item->optionGroups->pluck('name')->implode(', ') }}@endif</small></span>
                                        <span class="dine-picked" data-picked-count hidden></span>
                                        <span class="row-btn dine-plus" aria-hidden="true">+</span>
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

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
                @include('partials.number-stepper', ['name' => 'item_quantity', 'label' => __('dinein.quantity'), 'value' => 1, 'min' => 1, 'max' => 99])
                <div class="field">
                    <label for="item-note">{{ __('dinein.note') }}</label>
                    <textarea id="item-note" rows="2" maxlength="200" placeholder="{{ __('dinein.note_example') }}" data-item-note></textarea>
                </div>
                <small class="error" data-item-error hidden></small>
                <button class="btn" type="submit" data-item-add></button>
            </form>
        </dialog>

        <dialog id="dine-cart" class="dine-dialog" aria-labelledby="dine-cart-title" data-backdrop-close>
            <div class="stack">
                <div class="panel-head dialog-head">
                    <h2 id="dine-cart-title">{{ __('dinein.order_list') }}</h2>
                    <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
                </div>
                <p class="row-note" data-cart-empty>{{ __('dinein.order_empty') }}</p>
                <ul class="rows dine-lines" data-cart-lines></ul>
                <div class="picker-foot">
                    <span>
                        <small data-dine-count></small>
                        <strong data-dine-total></strong>
                    </span>
                    <button class="btn" type="button" data-cart-send>{{ __('dinein.order_send') }}</button>
                </div>
            </div>
        </dialog>
    @endif
@endsection
