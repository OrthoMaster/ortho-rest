@extends('layouts.super')

@section('title', $restaurant->name)

@section('content')
    <div class="page-head">
        @component('partials.page-title', ['title' => $restaurant->name, 'back' => back_url(route('super.restaurants.index'))])
            @if ($restaurant->isLocked())
                <span class="badge badge-bad">{{ __('ui.super.status_locked') }}</span>
            @else
                <span class="badge badge-ok">{{ __('ui.super.status_active') }}</span>
            @endif
        @endcomponent
        <div class="actions">
            @if ($restaurant->hasModule('website'))
                <a class="btn btn-quiet" href="{{ tenant_route('public.home', restaurant: $restaurant) }}">{{ __('ui.super.view_site') }}</a>
            @endif
            @if ($owner)
                <form method="post" action="{{ route('super.impersonate.start', $restaurant) }}">
                    @csrf
                    <button class="btn" type="submit">{{ __('ui.super.impersonate') }}</button>
                </form>
            @endif
        </div>
    </div>

    <section class="panel">
        <h2>{{ __('ui.super.info') }}</h2>
        @if ($owner)
            <p>{{ __('ui.super.owner_line', ['name' => $owner->name, 'email' => $owner->email]) }}</p>
            <p class="aside-link">{{ __('ui.super.owner_login', ['url' => route('tenant.login', ['resto' => $restaurant->slug])]) }}</p>
            @if ($owner->devices_locked)
                <form method="post" action="{{ route('super.restaurants.devices.unlock', $restaurant) }}" class="inline-form">
                    @csrf
                    <span>{{ __('ui.super.devices_locked') }}</span>
                    <button class="btn btn-quiet btn-sm" type="submit">{{ __('ui.super.devices_unlock') }}</button>
                </form>
            @endif
        @endif
        <form method="post" action="{{ route('super.restaurants.update', $restaurant) }}" class="stack narrow" data-guard>
            @csrf
            @method('put')
            @include('partials.field', ['name' => 'name', 'label' => __('ui.super.name'), 'value' => $restaurant->name])
            @include('partials.field', ['name' => 'slug', 'label' => __('ui.super.slug'), 'value' => $restaurant->slug])
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </form>
    </section>

    <section class="panel">
        <h2>{{ __('ui.logo.title') }}</h2>
        @include('partials.logo-form', [
            'restaurant' => $restaurant,
            'updateUrl' => route('super.logo.update', $restaurant),
            'destroyUrl' => route('super.logo.destroy', $restaurant),
        ])
    </section>

    <section class="panel">
        <h2>{{ __('ui.super.theme') }}</h2>
        <p class="row-note">{{ __('ui.super.theme_hint') }}</p>
        <form method="post" action="{{ route('super.theme.update', $restaurant) }}" class="stack" data-guard>
            @csrf
            @method('put')
            <div class="swatches">
                @include('partials.field', ['name' => 'primary', 'type' => 'color', 'label' => __('ui.super.theme_primary'), 'value' => $restaurant->theme['primary'] ?? \App\Tenancy\RestaurantTheme::PRIMARY])
                @include('partials.field', ['name' => 'secondary', 'type' => 'color', 'label' => __('ui.super.theme_secondary'), 'value' => $restaurant->theme['secondary'] ?? \App\Tenancy\RestaurantTheme::SECONDARY])
            </div>
            <button class="btn" type="submit">{{ __('ui.super.theme_save') }}</button>
        </form>
        @if ($restaurant->themeCss())
            <form method="post" action="{{ route('super.theme.destroy', $restaurant) }}">
                @csrf
                @method('delete')
                <button class="link" type="submit">{{ __('ui.super.theme_reset_action') }}</button>
            </form>
        @endif
    </section>

    {{--
        Chức năng: mỗi module một toggle, module phụ thuộc lùi vào dưới module cần có.
        Bấm Cập nhật mới lưu một lần; tắt module cha thì module con cũng tắt theo.
    --}}
    @php($definitions = $modules->definitions())
    <section class="panel stack w-lg">
        <div>
            <h2>{{ __('ui.super.modules') }}</h2>
            <small class="row-note">{{ __('ui.super.modules_hint') }}</small>
        </div>
        <form method="post" action="{{ route('super.modules.sync', $restaurant) }}" class="stack module-form" data-module-form data-guard
              data-confirm-title="{{ __('ui.super.modules_confirm_title') }}" data-confirm-text="{{ __('ui.super.modules_confirm', ['modules' => ':modules']) }}">
            @csrf
            @method('put')
            <ul class="module-tree">
                @php($parents = array_map(fn (array $definition) => $definition['depends'][0] ?? null, $definitions))
                @foreach ($definitions as $key => $definition)
                    @continue($parents[$key] !== null)
                    @include('super.restaurants.module-toggle', ['key' => $key, 'definition' => $definition, 'depth' => 0])
                    @foreach (array_keys($parents, $key, true) as $childKey)
                        @include('super.restaurants.module-toggle', ['key' => $childKey, 'definition' => $definitions[$childKey], 'depth' => 1, 'parent' => $key])
                    @endforeach
                @endforeach
            </ul>
            <div class="form-actions">
                <button class="btn" type="submit">{{ __('ui.super.modules_save') }}</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>{{ __('ui.super.domains') }}</h2>
        @if ($restaurant->domains->isEmpty())
            <p>{{ __('ui.super.domains_empty') }}</p>
        @else
            <ul class="rows">
                @foreach ($restaurant->domains as $domain)
                    <li>
                        <span class="row-name">{{ $domain->domain }}</span>
                        @if ($domain->verified_at)
                            <span class="badge badge-ok">{{ __('ui.super.domain_verified_label') }}</span>
                        @else
                            <span class="badge">{{ __('ui.super.domain_pending_label') }}</span>
                            <form method="post" action="{{ route('super.domains.verify', [$restaurant, $domain]) }}">
                                @csrf
                                <button class="link" type="submit">{{ __('ui.super.domain_verify') }}</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('super.domains.destroy', [$restaurant, $domain]) }}"
                              data-confirm-danger data-confirm="{{ __('ui.super.domain_remove_confirm', ['domain' => $domain->domain]) }}">
                            @csrf
                            @method('delete')
                            <button class="link link-danger" type="submit">{{ __('ui.super.domain_remove') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('super.domains.store', $restaurant) }}" class="stack narrow domain-form">
            @csrf
            @include('partials.field', ['name' => 'domain', 'label' => __('ui.super.domain'), 'hint' => __('ui.super.domain_hint')])
            <button class="btn btn-quiet" type="submit">{{ __('ui.super.domain_add') }}</button>
        </form>
    </section>

    <section class="panel">
        @if ($restaurant->isLocked())
            <form method="post" action="{{ route('super.restaurants.unlock', $restaurant) }}">
                @csrf
                <button class="btn" type="submit">{{ __('ui.super.unlock') }}</button>
            </form>
        @else
            <form method="post" action="{{ route('super.restaurants.lock', $restaurant) }}"
                  data-confirm-danger data-confirm="{{ __('ui.super.lock_confirm') }}">
                @csrf
                <button class="btn btn-danger" type="submit">{{ __('ui.super.lock') }}</button>
            </form>
        @endif
    </section>
@endsection
