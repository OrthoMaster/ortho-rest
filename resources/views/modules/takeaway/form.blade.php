@extends('layouts.admin')

@section('title', __('takeaway.add'))

@section('content')
    @include('partials.page-title', ['title' => __('takeaway.add'), 'back' => back_url(route('tenant.admin.takeaway.index'))])

    @if ($categories->isEmpty())
        <p class="empty">{{ __('takeaway.no_menu') }}</p>
    @else
        <form method="post" action="{{ route('tenant.admin.takeaway.store') }}" class="stack order-form" data-guard data-order-form data-count-label="{{ __('takeaway.item_count') }}">
            @csrf
            <section class="panel">
                <div class="panel-head">
                    <h2>{{ __('takeaway.items') }}</h2>
                    <button class="btn btn-sm" type="button" data-open-dialog="pick-dialog">{{ __('takeaway.pick_items') }}</button>
                </div>
                @error('items')<small class="error">{{ $message }}</small>@enderror
                <p class="row-note" data-order-empty>{{ __('takeaway.picked_empty') }}</p>
                <ul class="rows order-lines" data-order-lines></ul>
            </section>

            <section class="panel stack">
                <div class="form-row">
                    @include('partials.field', ['name' => 'customer_name', 'label' => __('reservation.customer_name')])
                    @include('partials.field', ['name' => 'customer_phone', 'type' => 'tel', 'label' => __('reservation.customer_phone')])
                </div>
                @include('partials.field', ['name' => 'pickup_at', 'type' => 'datetime-local', 'label' => __('takeaway.pickup_at'), 'value' => now()->addMinutes(30)->format('Y-m-d\TH:i')])
                @include('partials.textarea', ['name' => 'notes', 'label' => __('reservation.notes'), 'rows' => 2])
            </section>

            <div class="order-bar">
                <span>
                    <small data-order-count></small>
                    <strong data-order-total></strong>
                </span>
                <button class="btn" type="submit">{{ __('takeaway.save') }}</button>
            </div>

            <template data-order-line>
                <li data-item-id>
                    <span class="row-name"><span data-line-name></span><small data-line-price></small></span>
                    @include('modules.takeaway.stepper')
                    <strong class="line-total" data-line-total></strong>
                </li>
            </template>

            <dialog id="pick-dialog" class="picker">
                <div class="picker-head">
                    <div class="panel-head">
                        <h2>{{ __('takeaway.pick_items') }}</h2>
                        <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
                    </div>
                    <input type="search" data-pick-search autofocus placeholder="{{ __('menu.search') }}" aria-label="{{ __('menu.search') }}" autocomplete="off">
                </div>

                <div class="picker-body">
                    <p class="row-note" data-pick-empty hidden>{{ __('menu.search_empty') }}</p>
                    @foreach ($categories as $category)
                        <section data-pick-group>
                            <h3>{{ $category->name }}</h3>
                            <ul class="rows">
                                @foreach ($category->items as $item)
                                    <li @class(['is-sold-out' => $item->is_sold_out])
                                        data-pick-search-text="{{ $item->name }} {{ $category->name }}"
                                        @unless ($item->is_sold_out) data-item-id="{{ $item->id }}" data-name="{{ $item->name }}" data-price="{{ $item->price_takeaway }}" @endunless>
                                        <span class="row-name">{{ $item->name }}<small>{{ vnd($item->price_takeaway) }}</small></span>
                                        @if ($item->is_sold_out)
                                            <span class="badge badge-bad">{{ __('menu.sold_out') }}</span>
                                        @else
                                            <input type="hidden" name="items[{{ $item->id }}]" value="{{ (int) old('items.'.$item->id, 0) }}" data-qty>
                                            @include('modules.takeaway.stepper')
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>

                <div class="picker-foot">
                    <span>
                        <small data-order-count></small>
                        <strong data-order-total></strong>
                    </span>
                    <button class="btn" type="button" data-close-dialog>{{ __('takeaway.done') }}</button>
                </div>
            </dialog>
        </form>
    @endif
@endsection
