@extends('layouts.admin')

@section('title', $item->exists ? $item->name : __('menu.add_item'))

@section('content')
    @include('partials.page-title', ['title' => $item->exists ? $item->name : __('menu.add_item'), 'back' => back_url(route('tenant.admin.menu.index'))])

    @php($selectedCategory = old('menu_category_id', $item->menu_category_id ?? ($categories->isEmpty() ? 'new' : '')))

    <form method="post" enctype="multipart/form-data" class="panel narrow stack" data-guard
          action="{{ $item->exists ? route('tenant.admin.menu.items.update', $item) : route('tenant.admin.menu.items.store') }}">
        @csrf
        @if ($item->exists) @method('put') @endif
        @include('partials.image-upload', ['name' => 'image', 'label' => __('menu.image'), 'url' => $item->imageUrl(), 'removeName' => 'remove_image', 'hint' => __('ui.image.hint')])
        @include('partials.field', ['name' => 'name', 'label' => __('menu.item_name'), 'value' => $item->name])
        @include('partials.select', ['name' => 'menu_category_id', 'label' => __('menu.category'), 'value' => $selectedCategory, 'options' => $categories->pluck('name', 'id')->put('new', __('menu.new_category_option'))])
        <div data-new-category @if ((string) $selectedCategory !== 'new') hidden @endif>
            @include('partials.field', ['name' => 'new_category_name', 'label' => __('menu.new_category_name'), 'required' => false])
        </div>
        @include('partials.textarea', ['name' => 'description', 'label' => __('menu.description'), 'value' => $item->description])
        <div class="form-row">
            @include('partials.field', ['name' => 'price_dine_in', 'type' => 'number', 'label' => __('menu.price_dine_in'), 'value' => $item->price_dine_in, 'required' => false])
            @if (has_module('takeaway'))
                @include('partials.field', ['name' => 'price_takeaway', 'type' => 'number', 'label' => __('menu.price_takeaway'), 'value' => $item->price_takeaway, 'required' => false])
            @endif
        </div>
        <small class="row-note">{{ __('menu.price_hint') }}</small>

        <div class="field">
            <span class="field-label">{{ __('menu.tags') }}</span>
            @php($selectedTags = array_map('intval', old('tags', $item->tags->pluck('id')->all())))
            <div class="toggles">
                @forelse ($tags as $tag)
                    <label class="check"><input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTags, true))> @include('modules.menu.tag')</label>
                @empty
                    <small>{{ __('menu.tags_none_hint') }}</small>
                @endforelse
            </div>
            <p><a class="link" href="{{ route('tenant.admin.menu.tags.index') }}">{{ __('menu.manage_tags') }}</a></p>
        </div>

        <div class="toggles">
            <label class="check"><input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $item->is_visible))> {{ __('menu.visible') }}</label>
            <label class="check"><input type="checkbox" name="is_sold_out" value="1" @checked(old('is_sold_out', $item->is_sold_out))> {{ __('menu.sold_out') }}</label>
        </div>
        <button class="btn" type="submit">{{ __('menu.save_item') }}</button>
    </form>

    @if ($item->exists)
        <form method="post" action="{{ route('tenant.admin.menu.items.destroy', $item) }}" data-confirm-danger data-confirm="{{ __('menu.item_delete_confirm', ['name' => $item->name]) }}">
            @csrf
            @method('delete')
            <button class="link link-danger" type="submit">{{ __('menu.item_delete') }}</button>
        </form>
    @endif
@endsection
