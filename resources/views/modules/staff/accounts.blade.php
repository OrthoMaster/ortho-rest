@extends('layouts.admin')

@section('title', __('staff.accounts'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.staff') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.staff.accounts.create') }}">{{ __('staff.account_add') }}</a>
    </div>

    @include('modules.staff.subnav')

    <ul class="rows">
        @foreach ($accounts as $account)
            @php($owner = $account->isOwner())
            <li @class(['row-link' => ! $owner, 'is-muted' => ! $account->is_active])>
                <span class="row-name">
                    @if ($owner)
                        <strong>{{ $account->name }}</strong>
                    @else
                        <a href="{{ route('tenant.admin.staff.accounts.edit', $account) }}">{{ $account->name }}</a>
                    @endif
                    <small>{{ collect([$account->phone, $account->email])->filter()->implode(' · ') }}</small>
                    <span class="chip-list">
                        @if ($owner)
                            <span class="tag">{{ $account->role->label() }}</span>
                        @else
                            @forelse ($account->positions as $position)
                                <span class="tag">{{ $position->name }}</span>
                            @empty
                                <span class="tag is-empty">{{ __('staff.no_position') }}</span>
                            @endforelse
                            @if ($extra = count($account->permissions ?? []))
                                <span class="tag is-extra">{{ __('staff.extra_count', ['count' => $extra]) }}</span>
                            @endif
                        @endif
                    </span>
                </span>
                @unless ($account->is_active)
                    <span class="badge">{{ __('staff.inactive') }}</span>
                @endunless
                @unless ($owner)
                    <div class="row-tools">
                        @component('partials.kebab', ['label' => __('staff.actions_for', ['name' => $account->name])])
                            <a href="{{ route('tenant.admin.staff.accounts.edit', $account) }}">{{ __('staff.account_edit') }}</a>
                            @unless ($account->is(auth()->user()))
                                <form method="post" action="{{ route('tenant.admin.staff.accounts.active', $account) }}"
                                      data-confirm="{{ __($account->is_active ? 'staff.deactivate_confirm' : 'staff.activate_confirm', ['name' => $account->name]) }}"
                                      data-confirm-title="{{ __($account->is_active ? 'staff.deactivate' : 'staff.activate') }}?"
                                      data-confirm-ok="{{ __($account->is_active ? 'staff.deactivate' : 'staff.activate') }}" @if ($account->is_active) data-confirm-danger @endif>
                                    @csrf
                                    <button @class(['is-danger' => $account->is_active]) type="submit">{{ __($account->is_active ? 'staff.deactivate' : 'staff.activate') }}</button>
                                </form>
                            @endunless
                        @endcomponent
                    </div>
                @endunless
            </li>
        @endforeach
    </ul>
@endsection
