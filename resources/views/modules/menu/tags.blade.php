@extends('layouts.admin')

@section('title', __('menu.tags'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.menu') }}</h1>
    </div>

    @include('modules.menu.subnav', ['current' => 'tags'])

    <p class="lead">{{ __('menu.tag_hint') }}</p>

    @if ($tags->isEmpty())
        <p class="empty">{{ __('menu.tags_empty') }}</p>
    @else
        <section class="panel">
            <ul class="rows">
                @foreach ($tags as $tag)
                    <li class="row-link">
                        <span class="row-name"><a href="{{ route('tenant.admin.menu.tags.edit', $tag) }}">{{ $tag->name }}</a></span>
                        @include('modules.menu.tag')
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="panel">
        <h2>{{ __('menu.tag_add') }}</h2>
        <form method="post" action="{{ route('tenant.admin.menu.tags.store') }}" class="inline-form">
            @csrf
            <input name="name" maxlength="60" placeholder="{{ __('menu.tag_example') }}" aria-label="{{ __('menu.tag_name') }}" required>
            <button class="btn btn-quiet" type="submit">{{ __('menu.tag_add') }}</button>
        </form>
        @error('name')<small class="error">{{ $message }}</small>@enderror
    </section>
@endsection
