@extends('layouts.admin')

@php($editing = $account->exists)
@php($title = $editing ? __('staff.account_edit_title', ['name' => $account->name]) : __('staff.account_add'))

@section('title', $title)

@section('content')
    @include('partials.page-title', ['title' => $title, 'back' => route('tenant.admin.staff.accounts.index')])

    @php($chosen = array_map('intval', old('positions', $account->exists ? $account->positions->pluck('id')->all() : [])))
    @php($extras = old('permissions', $account->permissions ?? []))
    @php($extraCount = count(array_intersect($extras, \App\Support\Permissions::visibleKeys(tenant()))))

    <form method="post" action="{{ $editing ? route('tenant.admin.staff.accounts.update', $account) : route('tenant.admin.staff.accounts.store') }}" class="stack staff-form" data-guard data-extra-perms>
        @csrf
        @if ($editing) @method('put') @endif

        {{-- Ba tab trong cùng một form, Lưu một lần. Tab có lỗi tự mở. --}}
        <div class="subnav form-tabs" role="tablist" data-tabs>
            <button type="button" role="tab" data-tab="info">{{ __('staff.tab_account') }}</button>
            <button type="button" role="tab" data-tab="roles">{{ __('staff.tab_roles') }}<span class="badge" data-extra-count @if ($extraCount === 0) hidden @endif>{{ $extraCount }}</span></button>
            <button type="button" role="tab" data-tab="contract">{{ __('staff.contract') }}</button>
        </div>

        <section class="panel stack narrow-panel" role="tabpanel" data-tab-panel="info">
            @include('partials.field', ['name' => 'name', 'label' => __('staff.name'), 'value' => $account->name, 'maxlength' => 255, 'autocomplete' => 'off'])
            <div class="form-row">
                @include('partials.field', ['name' => 'phone', 'type' => 'tel', 'label' => __('staff.phone'), 'value' => $account->phone, 'autocomplete' => 'off'])
                @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('staff.email'), 'value' => $account->email, 'required' => false, 'autocomplete' => 'off'])
            </div>
            <small class="row-note">{{ __('staff.phone_hint') }} {{ __('staff.email_hint') }}</small>
            @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => $editing ? __('staff.new_password') : __('staff.password'),
                'required' => ! $editing, 'autocomplete' => 'new-password', 'hint' => $editing ? __('staff.new_password_hint') : __('staff.password_hint')])
            @if ($editing)
                <label class="toggle-row">
                    <span>{{ __('staff.active') }}<small data-active-hint>{{ __('staff.active_hint') }}</small></span>
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active))>
                </label>
            @endif
        </section>

        <div class="stack" role="tabpanel" data-tab-panel="roles" hidden>
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

            {{-- Quyền riêng: ngoại lệ ngoài chức vụ, ví dụ nhân viên được thêm một quyền của quản lý. --}}
            <section class="panel stack">
                <div>
                    <h2>{{ __('staff.extra_permissions') }}</h2>
                    <small class="section-hint">{{ __('staff.extra_permissions_hint') }}</small>
                </div>
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
            </section>
        </div>

        {{-- Hợp đồng: bộ phận, ngày bắt đầu; hàng dưới ba ô số thẳng hàng. --}}
        <section class="panel stack narrow-panel" role="tabpanel" data-tab-panel="contract" hidden>
            <small class="section-hint">{{ __('staff.contract_hint') }}</small>
            <div class="form-row form-row-2">
                @include('partials.select', ['name' => 'department_id', 'label' => __('staff.department'), 'value' => $account->department_id, 'required' => false,
                    'options' => ['' => __('staff.no_department')] + $departments->pluck('name', 'id')->all()])
                @include('partials.date-picker', ['name' => 'started_on', 'label' => __('staff.started_on'), 'value' => $account->started_on?->toDateString(), 'withYear' => true, 'placeholder' => __('staff.not_set')])
            </div>
            <div class="form-row form-row-3">
                @include('partials.field', ['name' => 'paid_leave_per_month', 'type' => 'number', 'label' => __('staff.paid_leave_per_month'), 'required' => false,
                    'value' => $account->paid_leave_per_month !== null ? rtrim(rtrim(number_format($account->paid_leave_per_month, 2, '.', ''), '0'), '.') : null,
                    'step' => '0.25', 'min' => 0, 'max' => 10])
                @include('partials.field', ['name' => 'contract_hours', 'type' => 'number', 'label' => __('staff.contract_hours'), 'required' => false,
                    'value' => $account->contract_hours !== null ? rtrim(rtrim(number_format($account->contract_hours, 1, '.', ''), '0'), '.') : null,
                    'step' => '0.5', 'min' => 0, 'max' => 400])
                @include('partials.select', ['name' => 'contract_period', 'label' => __('staff.contract_period'), 'value' => $account->contract_period ?? 'week', 'required' => false, 'options' => __('staff.periods')])
            </div>
            <small class="row-note">{{ __('staff.paid_leave_hint') }} {{ __('staff.contract_hours_hint') }}</small>
        </section>

        <div>
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </div>
    </form>
@endsection
