@extends('layouts.admin')

@section('title', __('modules.menu'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.menu') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.menu.items.create') }}">{{ __('menu.add_item') }}</a>
    </div>

    @include('partials.subnav', ['links' => [
        ['url' => route('tenant.admin.menu.index'), 'label' => __('menu.all'), 'active' => $menu === 'all'],
        ['url' => route('tenant.admin.menu.index', ['menu' => 'dine_in']), 'label' => __('menu.dine_in'), 'active' => $menu === 'dine_in'],
        ['url' => route('tenant.admin.menu.index', ['menu' => 'takeaway']), 'label' => __('menu.takeaway'), 'active' => $menu === 'takeaway'],
    ]])

    @if ($categories->isEmpty())
        <p class="empty">{{ __('menu.empty') }}</p>
    @else
        <div class="search">
            <input type="search" data-search-input placeholder="{{ __('menu.search') }}" aria-label="{{ __('menu.search') }}" autocomplete="off">
        </div>
        <p class="empty" data-search-empty hidden>{{ __('menu.search_empty') }}</p>
    @endif

    @foreach ($categories as $category)
        <section class="panel" data-search-group="{{ $category->name }}">
            <div class="category-head">
                <details class="category-edit">
                    <summary>
                        <h2>{{ $category->name }}</h2>
                        @unless ($category->is_visible)<span class="badge">{{ __('menu.hidden') }}</span>@endunless
                        <small>{{ __('menu.category_edit') }}</small>
                    </summary>
                    <form method="post" action="{{ route('tenant.admin.menu.categories.update', $category) }}" class="inline-form">
                        @csrf
                        @method('put')
                        <input name="name" value="{{ $category->name }}" aria-label="{{ __('menu.category_name') }}" required>
                        <label class="check"><input type="checkbox" name="is_visible" value="1" @checked($category->is_visible)> {{ __('menu.visible') }}</label>
                        <button class="btn btn-quiet" type="submit">{{ __('ui.common.save') }}</button>
                    </form>
                    <form method="post" action="{{ route('tenant.admin.menu.categories.destroy', $category) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('menu.category_delete_confirm', ['name' => $category->name]) }}">
                        @csrf
                        @method('delete')
                        <button class="link link-danger" type="submit">{{ __('menu.category_delete') }}</button>
                    </form>
                </details>
                <div class="row-actions">
                    @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                        <form method="post" action="{{ route('tenant.admin.menu.categories.move', $category) }}">
                            @csrf
                            <input type="hidden" name="direction" value="{{ $direction }}">
                            <button class="icon-btn" type="submit" aria-label="{{ __('menu.move_'.$direction) }}">{{ $arrow }}</button>
                        </form>
                    @endforeach
                </div>
            </div>

            @if ($category->items->isEmpty())
                <p class="row-note" data-search-hide>{{ __('menu.category_empty') }}</p>
            @else
                <ul class="rows">
                    @foreach ($category->items as $item)
                        <li @class(['is-dim' => ! $item->is_visible]) data-search-item="{{ $item->name }} {{ $item->description }}">
                            <span class="thumb">
                                @if ($item->imageUrl())<img src="{{ $item->imageUrl() }}" alt="">@endif
                            </span>
                            <span class="row-name">
                                <a href="{{ route('tenant.admin.menu.items.edit', $item) }}">{{ $item->name }}</a>
                                <small>
                                    @if ($item->price_dine_in !== null){{ __('menu.dine_in') }}: {{ vnd($item->price_dine_in) }}@endif
                                    @if ($item->price_dine_in !== null && $item->price_takeaway !== null)<br>@endif
                                    @if ($item->price_takeaway !== null){{ __('menu.takeaway') }}: {{ vnd($item->price_takeaway) }}@endif
                                </small>
                            </span>
                            @if ($item->is_sold_out)<span class="badge badge-bad">{{ __('menu.sold_out') }}</span>@endif
                            @unless ($item->is_visible)<span class="badge">{{ __('menu.hidden') }}</span>@endunless
                            <div class="row-actions">
                                <form method="post" action="{{ route('tenant.admin.menu.items.toggle', $item) }}">
                                    @csrf
                                    <input type="hidden" name="field" value="is_sold_out">
                                    <button class="link" type="submit">{{ $item->is_sold_out ? __('menu.mark_available') : __('menu.mark_sold_out') }}</button>
                                </form>
                                <form method="post" action="{{ route('tenant.admin.menu.items.toggle', $item) }}">
                                    @csrf
                                    <input type="hidden" name="field" value="is_visible">
                                    <button class="link" type="submit">{{ $item->is_visible ? __('menu.hide') : __('menu.show') }}</button>
                                </form>
                                @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                                    <form method="post" action="{{ route('tenant.admin.menu.items.move', $item) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="{{ $direction }}">
                                        <button class="icon-btn" type="submit" aria-label="{{ __('menu.move_'.$direction) }}">{{ $arrow }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p data-search-hide><a class="link" href="{{ route('tenant.admin.menu.items.create', ['category' => $category->id]) }}">{{ __('menu.add_item_here') }}</a></p>
        </section>
    @endforeach

    <section class="panel">
        <h2>{{ __('menu.add_category') }}</h2>
        <form method="post" action="{{ route('tenant.admin.menu.categories.store') }}" class="inline-form">
            @csrf
            <input name="name" placeholder="{{ __('menu.category_example') }}" aria-label="{{ __('menu.category_name') }}" required>
            <button class="btn btn-quiet" type="submit">{{ __('menu.add_category') }}</button>
        </form>
        @error('name')<small class="error">{{ $message }}</small>@enderror
    </section>
@endsection
