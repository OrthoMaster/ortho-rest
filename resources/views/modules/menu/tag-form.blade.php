@extends('layouts.admin')

@section('title', $tag->name)

@section('content')
    <h1>{{ $tag->name }}</h1>

    <form method="post" enctype="multipart/form-data" action="{{ route('tenant.admin.menu.tags.update', $tag) }}" class="panel narrow stack">
        @csrf
        @method('put')
        @include('partials.field', ['name' => 'name', 'label' => __('menu.tag_name'), 'value' => $tag->name])
        @include('partials.image-upload', ['name' => 'image', 'label' => __('menu.tag_image'), 'url' => $tag->imageUrl(), 'square' => true, 'removeName' => 'remove_image', 'hint' => __('ui.logo.hint')])
        <label class="check"><input type="checkbox" name="image_only" value="1" @checked(old('image_only', $tag->image_only))> {{ __('menu.tag_image_only') }}</label>
        <button class="btn" type="submit">{{ __('menu.tag_save') }}</button>
    </form>

    <form method="post" action="{{ route('tenant.admin.menu.tags.destroy', $tag) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('menu.tag_delete_confirm', ['name' => $tag->name]) }}">
        @csrf
        @method('delete')
        <button class="link link-danger" type="submit">{{ __('menu.tag_delete') }}</button>
    </form>
@endsection
