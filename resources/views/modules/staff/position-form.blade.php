@extends('layouts.admin')

@php($editing = $position->exists)
@php($title = $editing ? __('staff.position_edit_title', ['name' => $position->name]) : __('staff.position_add'))

@section('title', $title)

@section('content')
    @include('partials.page-title', ['title' => $title, 'back' => route('tenant.admin.staff.positions.index')])

    @php($chosen = old('permissions', $position->permissions ?? []))

    <form method="post" action="{{ $editing ? route('tenant.admin.staff.positions.update', $position) : route('tenant.admin.staff.positions.store') }}" class="stack position-form" data-guard>
        @csrf
        @if ($editing) @method('put') @endif

        <section class="panel stack">
            @include('partials.field', ['name' => 'name', 'label' => __('staff.position_name'), 'value' => $position->name, 'maxlength' => 60, 'autocomplete' => 'off', 'hint' => __('staff.position_name_hint')])
        </section>

        <section class="stack">
            <div>
                <h2>{{ __('staff.permissions') }}</h2>
                <small class="section-hint">{{ __('staff.permissions_hint') }}</small>
            </div>
            @error('permissions')<small class="error">{{ $message }}</small>@enderror
            <div class="permission-groups">
                @foreach ($groups as $module => $keys)
                    <section class="panel stack permission-group">
                        <h3>{{ __('permissions.groups.'.$module) }}</h3>
                        <div class="toggle-list">
                            @foreach (array_keys($keys) as $key)
                                <label class="toggle-row">
                                    <span>{{ \App\Support\Permissions::label($key) }}</span>
                                    <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $chosen, true))>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <div>
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </div>
    </form>
@endsection
