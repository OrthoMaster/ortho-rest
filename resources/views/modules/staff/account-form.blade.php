@extends('layouts.admin')

@php($editing = $account->exists)
@php($title = $editing ? __('staff.account_edit_title', ['name' => $account->name]) : __('staff.account_add'))

@section('title', $title)

@section('content')
    @include('partials.page-title', ['title' => $title, 'back' => route('tenant.admin.staff.accounts.index')])

    @php($chosen = array_map('intval', old('positions', $account->exists ? $account->positions->pluck('id')->all() : [])))
    @php($extras = old('permissions', $account->permissions ?? []))

    <form method="post" action="{{ $editing ? route('tenant.admin.staff.accounts.update', $account) : route('tenant.admin.staff.accounts.store') }}" class="stack staff-form" data-guard data-extra-perms>
        @csrf
        @if ($editing) @method('put') @endif

        <div class="staff-grid">
            <section class="panel stack">
                <h2>{{ __('staff.info') }}</h2>
                @include('partials.field', ['name' => 'name', 'label' => __('staff.name'), 'value' => $account->name, 'maxlength' => 255, 'autocomplete' => 'off'])
                @include('partials.field', ['name' => 'phone', 'type' => 'tel', 'label' => __('staff.phone'), 'value' => $account->phone, 'autocomplete' => 'off', 'hint' => __('staff.phone_hint')])
                @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('staff.email'), 'value' => $account->email, 'required' => false, 'autocomplete' => 'off', 'hint' => __('staff.email_hint')])
                @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => $editing ? __('staff.new_password') : __('staff.password'),
                    'required' => ! $editing, 'autocomplete' => 'new-password', 'hint' => $editing ? __('staff.new_password_hint') : __('staff.password_hint')])
            </section>

            <section class="panel stack">
                <div>
                    <h2>{{ __('staff.positions') }}</h2>
                    <small class="section-hint">{{ __('staff.positions_hint') }}</small>
                </div>
                @if ($positions->isEmpty())
                    <p class="empty">{{ __('staff.no_positions') }}</p>
                @else
                    <div class="toggle-list">
                        @foreach ($positions as $position)
                            <label class="toggle-row">
                                <span>
                                    {{ $position->name }}
                                    <small>{{ __('staff.permission_count', ['count' => count($position->permissions)]) }}</small>
                                </span>
                                <input type="checkbox" name="positions[]" value="{{ $position->id }}" @checked(in_array($position->id, $chosen, true)) data-position-perms='@json($position->permissions)'>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('positions')<small class="error">{{ $message }}</small>@enderror
            </section>
        </div>

        {{-- Hợp đồng: bộ phận, ngày bắt đầu, phép có lương, giờ làm. --}}
        <section class="panel stack">
            <div>
                <h2>{{ __('staff.contract') }}</h2>
                <small class="section-hint">{{ __('staff.contract_hint') }}</small>
            </div>
            <div class="form-row">
                @include('partials.select', ['name' => 'department_id', 'label' => __('staff.department'), 'value' => $account->department_id, 'required' => false,
                    'options' => ['' => __('staff.no_department')] + $departments->pluck('name', 'id')->all()])
                @include('partials.date-picker', ['name' => 'started_on', 'label' => __('staff.started_on'), 'value' => $account->started_on?->toDateString(), 'withYear' => true, 'placeholder' => __('staff.not_set')])
            </div>
            @include('partials.field', ['name' => 'paid_leave_per_month', 'type' => 'number', 'label' => __('staff.paid_leave_per_month'), 'required' => false,
                'value' => $account->paid_leave_per_month !== null ? rtrim(rtrim(number_format($account->paid_leave_per_month, 2, '.', ''), '0'), '.') : null,
                'step' => '0.25', 'min' => 0, 'max' => 10, 'hint' => __('staff.paid_leave_hint')])
            <div class="form-row">
                @include('partials.field', ['name' => 'contract_hours', 'type' => 'number', 'label' => __('staff.contract_hours'), 'required' => false,
                    'value' => $account->contract_hours !== null ? rtrim(rtrim(number_format($account->contract_hours, 1, '.', ''), '0'), '.') : null,
                    'step' => '0.5', 'min' => 0, 'max' => 400, 'hint' => __('staff.contract_hours_hint')])
                @include('partials.select', ['name' => 'contract_period', 'label' => __('staff.contract_period'), 'value' => $account->contract_period ?? 'week', 'required' => false, 'options' => __('staff.periods')])
            </div>
        </section>

        {{-- Quyền riêng: ngoại lệ ngoài chức vụ, ví dụ nhân viên được thêm một quyền của quản lý. --}}
        @php($extraCount = count(array_intersect($extras, \App\Support\Permissions::visibleKeys(tenant()))))
        <details class="panel extra-perms" @if ($extraCount > 0 || $errors->has('permissions.*')) open @endif>
            <summary>
                <span>
                    <strong>{{ __('staff.extra_permissions') }}</strong>
                    <small>{{ __('staff.extra_permissions_hint') }}</small>
                </span>
                <span class="badge" data-extra-count @if ($extraCount === 0) hidden @endif>{{ $extraCount }}</span>
            </summary>
            @error('permissions.*')<small class="error">{{ $message }}</small>@enderror
            <div class="permission-groups">
                @foreach ($groups as $module => $keys)
                    <section class="stack permission-group">
                        <h3>{{ __('permissions.groups.'.$module) }}</h3>
                        <div class="toggle-list">
                            @foreach (array_keys($keys) as $key)
                                <label class="toggle-row">
                                    <span>
                                        {{ \App\Support\Permissions::label($key) }}
                                        <small data-from-position hidden>{{ __('staff.from_position') }}</small>
                                    </span>
                                    <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $extras, true)) data-extra-perm>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </details>

        <div>
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </div>
    </form>
@endsection
