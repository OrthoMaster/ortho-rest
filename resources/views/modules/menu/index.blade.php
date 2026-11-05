@extends('layouts.admin')

@section('title', __('modules.menu'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.menu') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.menu.items.create') }}">{{ __('menu.add_item') }}</a>
    </div>

    @include('modules.menu.subnav', ['current' => $menu])

    @if ($categories->isEmpty())
        <p class="empty">{{ __('menu.empty') }}</p>
    @else
        <div class="search">
            <input type="search" data-search-input placeholder="{{ __('menu.search') }}" aria-label="{{ __('menu.search') }}" autocomplete="off">
        </div>
        <p class="empty" data-search-empty hidden>{{ __('menu.search_empty') }}</p>
    @endif

    <div class="category-list" data-sortable data-sort-url="{{ route('tenant.admin.menu.categories.reorder') }}" data-sort-press=".category-head">
    @foreach ($categories as $category)
        <section class="panel menu-category" data-sort-id="{{ $category->id }}" data-search-group="{{ $category->name }}" data-collapse-key="menu-category-{{ $category->id }}">
            <div class="category-head">
                <h2 class="category-title">
                    <button class="category-toggle" type="button" aria-expanded="true" aria-controls="category-{{ $category->id }}" aria-label="{{ __('menu.toggle_category', ['name' => $category->name]) }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        <span class="category-name">{{ $category->name }}</span>
                        <span class="category-count">{{ __('menu.item_count', ['count' => $category->items->count()]) }}</span>
                    </button>
                </h2>
                @unless ($category->is_visible)<span class="badge">{{ __('menu.hidden') }}</span>@endunless
                <div class="row-tools">
                    @include('partials.drag-handle', ['label' => __('menu.drag_category', ['name' => $category->name])])
                    @component('partials.kebab', ['label' => __('menu.category_actions', ['name' => $category->name])])
                        <button type="button" data-open-dialog="category-dialog-{{ $category->id }}">{{ __('menu.category_edit') }}</button>
                    @endcomponent
                </div>
            </div>

            <dialog id="category-dialog-{{ $category->id }}">
                <form method="post" action="{{ route('tenant.admin.menu.categories.update', $category) }}" class="stack">
                    @csrf
                    @method('put')
                    <h2>{{ __('menu.category_edit') }}</h2>
                    <div class="field">
                        <label for="category-name-{{ $category->id }}">{{ __('menu.category_name') }}</label>
                        <input id="category-name-{{ $category->id }}" name="name" value="{{ $category->name }}" required>
                    </div>
                    <div class="field">
                        <label for="category-description-{{ $category->id }}">{{ __('menu.description') }}</label>
                        <textarea id="category-description-{{ $category->id }}" name="description" rows="3" maxlength="1000" placeholder="{{ __('menu.category_description_example') }}">{{ $category->description }}</textarea>
                    </div>
                    <label class="check"><input type="checkbox" name="is_visible" value="1" @checked($category->is_visible)> {{ __('menu.visible') }}</label>
                    <div class="dialog-foot">
                        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                        <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
                    </div>
                </form>
                <form method="post" action="{{ route('tenant.admin.menu.categories.destroy', $category) }}" class="dialog-delete" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('menu.category_delete_confirm', ['name' => $category->name]) }}">
                    @csrf
                    @method('delete')
                    <button class="link link-danger" type="submit">{{ __('menu.category_delete') }}</button>
                </form>
            </dialog>

            <div class="category-body" id="category-{{ $category->id }}">
                @if ($category->description)
                    <p class="category-description">{{ $category->description }}</p>
                @endif
                @if ($category->items->isEmpty())
                    <p class="row-note" data-search-hide>{{ __('menu.category_empty') }}</p>
                @else
                    <ul class="rows menu-items" data-sortable data-sort-url="{{ route('tenant.admin.menu.items.reorder', $category) }}">
                        @foreach ($category->items as $item)
                            <li @class(['row-link', 'is-dim' => ! $item->is_visible, 'is-sold-out' => $item->is_sold_out]) data-sort-id="{{ $item->id }}" data-search-item="{{ collect([$item->name, $item->description, ...$item->tags->pluck('name')])->filter()->join(' ') }}">
                                <span class="thumb">
                                    @if ($item->imageUrl())
                                        <img src="{{ $item->imageUrl() }}" alt="">
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="m4 17 5-4.5 3.5 3 3-2.5L20 17"/></svg>
                                    @endif
                                    @unless ($item->is_visible)
                                        <span class="thumb-hidden">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2"/><path d="M6.6 6.6C3.7 8.4 2 12 2 12s3.5 7 10 7c1.9 0 3.6-.6 5-1.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>
                                        </span>
                                    @endunless
                                </span>
                                <span class="row-name">
                                    <a href="{{ route('tenant.admin.menu.items.edit', $item) }}">{{ $item->name }}</a>
                                    <small>
                                        @if ($menu === 'all')
                                            @if ($item->price_dine_in !== null){{ __('menu.dine_in') }}: {{ vnd($item->price_dine_in) }}@endif
                                            @if ($item->price_dine_in !== null && $item->price_takeaway !== null)<br>@endif
                                            @if ($item->price_takeaway !== null){{ __('menu.takeaway') }}: {{ vnd($item->price_takeaway) }}@endif
                                        @else
                                            {{ vnd($item->{"price_$menu"}) }}
                                        @endif
                                    </small>
                                </span>
                                <div class="row-tools">
                                    @include('partials.drag-handle', ['label' => __('menu.drag_item', ['name' => $item->name])])
                                    @component('partials.kebab', ['label' => __('menu.item_actions', ['name' => $item->name])])
                                        <form method="post" action="{{ route('tenant.admin.menu.items.toggle', $item) }}">
                                            @csrf
                                            <input type="hidden" name="field" value="is_sold_out">
                                            <button type="submit">{{ $item->is_sold_out ? __('menu.mark_available') : __('menu.mark_sold_out') }}</button>
                                        </form>
                                        <form method="post" action="{{ route('tenant.admin.menu.items.toggle', $item) }}">
                                            @csrf
                                            <input type="hidden" name="field" value="is_visible">
                                            <button type="submit">{{ $item->is_visible ? __('menu.hide') : __('menu.show') }}</button>
                                        </form>
                                    @endcomponent
                                </div>
                                @if ($item->tags->isNotEmpty() || $item->is_sold_out || ! $item->is_visible)
                                    <div class="row-meta">
                                        <span class="row-tags">
                                            @foreach ($item->tags as $tag)
                                                <span @class(['tag-slot', 'tag-extra' => $loop->index >= 2])>@include('modules.menu.tag')</span>
                                            @endforeach
                                            @if ($item->tags->count() > 2)
                                                <span class="tag tag-more" title="{{ $item->tags->slice(2)->pluck('name')->join(', ') }}" aria-label="{{ __('menu.more_tags', ['count' => $item->tags->count() - 2]) }}">+{{ $item->tags->count() - 2 }}</span>
                                            @endif
                                        </span>
                                        <span class="row-status">
                                            @if ($item->is_sold_out)<span class="badge badge-bad">{{ __('menu.sold_out') }}</span>@endif
                                            @unless ($item->is_visible)<span class="badge">{{ __('menu.hidden') }}</span>@endunless
                                        </span>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p data-search-hide><a class="link" href="{{ route('tenant.admin.menu.items.create', ['category' => $category->id]) }}">{{ __('menu.add_item_here') }}</a></p>
            </div>
        </section>
    @endforeach
    </div>
@endsection
