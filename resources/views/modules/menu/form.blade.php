@extends('layouts.admin')

@section('title', $item->exists ? $item->name : __('menu.add_item'))

@section('content')
    <h1>{{ $item->exists ? $item->name : __('menu.add_item') }}</h1>

    @if ($categories->isEmpty())
        <p class="empty">{{ __('menu.need_category') }} <a href="{{ route('tenant.admin.menu.index') }}">{{ __('menu.go_add_category') }}</a></p>
    @else
        <form method="post" enctype="multipart/form-data" class="panel narrow stack"
              action="{{ $item->exists ? route('tenant.admin.menu.items.update', $item) : route('tenant.admin.menu.items.store') }}">
            @csrf
            @if ($item->exists) @method('put') @endif
            @include('partials.field', ['name' => 'name', 'label' => __('menu.item_name'), 'value' => $item->name])
            @include('partials.select', ['name' => 'menu_category_id', 'label' => __('menu.category'), 'value' => $item->menu_category_id, 'options' => $categories->pluck('name', 'id')])
            @include('partials.textarea', ['name' => 'description', 'label' => __('menu.description'), 'value' => $item->description])
            <div class="form-row">
                @include('partials.field', ['name' => 'price_dine_in', 'type' => 'number', 'label' => __('menu.price_dine_in'), 'value' => $item->price_dine_in, 'required' => false])
                @if (has_module('takeaway'))
                    @include('partials.field', ['name' => 'price_takeaway', 'type' => 'number', 'label' => __('menu.price_takeaway'), 'value' => $item->price_takeaway, 'required' => false])
                @endif
            </div>
            <small class="row-note">{{ __('menu.price_hint') }}</small>
            @if ($item->imageUrl())
                <div class="current-image">
                    <span class="thumb thumb-lg"><img src="{{ $item->imageUrl() }}" alt=""></span>
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> {{ __('menu.remove_image') }}</label>
                </div>
            @endif
            @include('partials.field', ['name' => 'image', 'type' => 'file', 'accept' => 'image/png,image/jpeg,image/webp', 'label' => __('menu.image'), 'required' => false, 'hint' => __('ui.logo.hint')])
            <label class="check"><input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $item->is_visible))> {{ __('menu.visible') }}</label>
            <label class="check"><input type="checkbox" name="is_sold_out" value="1" @checked(old('is_sold_out', $item->is_sold_out))> {{ __('menu.sold_out') }}</label>
            <button class="btn" type="submit">{{ __('menu.save_item') }}</button>
        </form>

        @if ($item->exists)
            <form method="post" action="{{ route('tenant.admin.menu.items.destroy', $item) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('menu.item_delete_confirm', ['name' => $item->name]) }}">
                @csrf
                @method('delete')
                <button class="link link-danger" type="submit">{{ __('menu.item_delete') }}</button>
            </form>
        @endif
    @endif
@endsection
