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

    <section class="panel">
        <h2>{{ __('ui.super.modules') }}</h2>
        <ul class="rows">
            @foreach ($modules->definitions() as $key => $definition)
                @php($on = $modules->isEnabled($restaurant, $key))
                @php($missing = $modules->missingDependencies($restaurant, $key))
                @php($dependents = array_values(array_filter($modules->dependentsOf($key), fn ($dependent) => $modules->isEnabled($restaurant, $dependent))))
                <li>
                    <span class="row-name">{{ __($definition['name']) }}</span>
                    @if ($modules->isCore($key))
                        <span class="row-note">{{ __('ui.super.module_always_on') }}</span>
                    @elseif (! $modules->isAvailable($key))
                        <span class="row-note">{{ __('ui.super.module_phase2') }}</span>
                    @else
                        @if ($on)
                            <span class="badge badge-ok">{{ __('ui.super.module_on') }}</span>
                        @endif
                        @if ($missing)
                            <span class="row-note">{{ __('ui.super.module_needs_label', ['modules' => implode(', ', array_map(fn ($dependency) => __($modules->definition($dependency)['name']), $missing))]) }}</span>
                        @endif
                        @php($confirm = $dependents
                            ? __('ui.super.module_disable_confirm_with', ['module' => __($definition['name']), 'modules' => implode(', ', array_map(fn ($dependent) => __($modules->definition($dependent)['name']), $dependents))])
                            : __('ui.super.module_disable_confirm', ['module' => __($definition['name'])]))
                        <form method="post" action="{{ route('super.modules.update', [$restaurant, $key]) }}"
                              @if ($on) onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ $confirm }}" @endif>
                            @csrf
                            @method('put')
                            <input type="hidden" name="enabled" value="{{ $on ? 0 : 1 }}">
                            <button type="submit" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="{{ __($definition['name']) }}"
                                    @class(['switch', 'is-on' => $on]) @disabled(! $on && $missing)>
                                <span>{{ $on ? __('ui.super.module_disable') : __('ui.super.module_enable') }}</span>
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
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
                              onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('ui.super.domain_remove_confirm', ['domain' => $domain->domain]) }}">
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
                  onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('ui.super.lock_confirm') }}">
                @csrf
                <button class="btn btn-danger" type="submit">{{ __('ui.super.lock') }}</button>
            </form>
        @endif
    </section>
@endsection
