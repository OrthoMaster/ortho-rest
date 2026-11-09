@extends('layouts.admin')

@php($title = __('menu.options_title', ['name' => $item->name]))

@section('title', $title)

@section('content')
    @include('partials.page-title', ['title' => $title, 'back' => route('tenant.admin.menu.items.edit', $item)])

    <p class="lead">{{ __('menu.options_lead') }}</p>

    @if ($sources->isNotEmpty())
        <form method="post" action="{{ route('tenant.admin.menu.items.options.copy', $item) }}" class="panel option-copy">
            @csrf
            <div class="field">
                <label for="copy-from">{{ __('menu.options_copy_from') }}</label>
                <select id="copy-from" name="from" required>
                    <option value="">{{ __('menu.options_copy_pick') }}</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}">{{ $source->name }} · {{ $source->category?->name }}</option>
                    @endforeach
                </select>
                <small>{{ __('menu.options_copy_hint') }}</small>
            </div>
            <button class="btn btn-quiet" type="submit">{{ __('menu.options_copy') }}</button>
        </form>
    @endif

    @php($groups = old('groups', $item->optionGroups->mapWithKeys(fn ($group) => ['g'.$group->id => [
        'name' => $group->name,
        'required' => $group->is_required,
        'multiple' => $group->is_multiple,
        'options' => $group->options->mapWithKeys(fn ($option) => ['o'.$option->id => ['name' => $option->name, 'price' => $option->price]])->all(),
    ]])->all()))

    <form method="post" action="{{ route('tenant.admin.menu.items.options.update', $item) }}" class="stack" data-guard data-option-editor>
        @csrf
        @method('put')

        <div class="stack" data-option-groups>
            @foreach ($groups as $key => $group)
                @include('modules.menu.option-group', ['key' => $key, 'group' => $group])
            @endforeach
        </div>
        <p class="empty" data-options-empty @if ($groups !== []) hidden @endif>{{ __('menu.options_empty') }}</p>

        <div class="option-actions">
            <button class="btn btn-quiet" type="button" data-add-group>{{ __('menu.option_group_add') }}</button>
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </div>

        <template data-group-template>@include('modules.menu.option-group', ['key' => '__G__', 'group' => null])</template>
        <template data-option-template>@include('modules.menu.option-row', ['key' => '__G__', 'optionKey' => '__O__', 'option' => ['name' => '', 'price' => 0]])</template>
    </form>
@endsection
