@extends('layouts.admin')

@section('title', __('ui.profile.title'))

@section('content')
    <h1>{{ __('ui.profile.title') }}</h1>

    <div class="profile-grid">
    <div class="profile-col">
    <section class="panel">
        <h2>{{ __('ui.profile.info') }}</h2>
        <form method="post" action="{{ route('tenant.admin.profile.update') }}" class="stack" data-guard>
            @csrf
            @method('put')
            @include('partials.field', ['name' => 'name', 'label' => __('ui.profile.name'), 'value' => $user->name, 'autocomplete' => 'name'])
            @include('partials.field', ['name' => 'phone', 'type' => 'tel', 'label' => __('staff.phone'), 'value' => $user->phone, 'autocomplete' => 'tel', 'required' => ! $user->isOwner()])
            @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.profile.email'), 'value' => $user->email, 'autocomplete' => 'username', 'required' => $user->isOwner()])
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
        </form>
    </section>

    <section class="panel">
        <h2>{{ __('ui.profile.change_password') }}</h2>
        <form method="post" action="{{ route('tenant.admin.profile.password') }}" class="stack" data-guard>
            @csrf
            @method('put')
            @include('partials.field', ['name' => 'current_password', 'type' => 'password', 'label' => __('ui.profile.current_password'), 'autocomplete' => 'current-password'])
            @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.password.new'), 'autocomplete' => 'new-password'])
            @include('partials.field', ['name' => 'password_confirmation', 'type' => 'password', 'label' => __('ui.password.confirm'), 'autocomplete' => 'new-password'])
            <button class="btn" type="submit">{{ __('ui.profile.password_submit') }}</button>
        </form>
    </section>

    {{-- Thông báo đẩy: số máy đã bật, gửi thử để kiểm tra máy có nhận được không. --}}
    <section class="panel stack" id="push">
        <h2>{{ __('push.profile_title') }}</h2>
        @if (! \App\Push\WebPush::enabled())
            <p class="row-note">{{ __('push.test_no_keys') }}</p>
        @else
            <p class="row-note">{{ $pushDevices > 0 ? trans_choice('push.profile_devices', $pushDevices, ['count' => $pushDevices]) : __('push.test_no_device') }}</p>
            @if ($pushDevices > 0)
                <form method="post" action="{{ route('tenant.admin.push.test') }}">
                    @csrf
                    <button class="btn btn-quiet" type="submit">{{ __('push.test') }}</button>
                </form>
            @endif
        @endif
    </section>

    </div>

    <section class="panel" id="devices">
        <h2>{{ __('ui.devices.title') }}</h2>
        @if ($impersonating)
            <p class="row-note">{{ __('ui.devices.impersonating') }}</p>
        @endif
        <ul class="rows">
            @foreach ($devices as $device)
                <li>
                    <span class="row-name">
                        {{ $device->name }}
                        <small>{{ __('ui.devices.seen', ['time' => $device->last_seen_at?->format('H:i d/m/Y'), 'ip' => $device->ip_address]) }}</small>
                    </span>
                    @if ($device->id === $currentDevice)
                        <span class="badge badge-ok">{{ __('ui.devices.current') }}</span>
                    @else
                        <form method="post" action="{{ route('tenant.admin.profile.devices.destroy', $device->id) }}" data-confirm-danger data-confirm="{{ __('ui.devices.revoke_confirm', ['name' => $device->name]) }}">
                            @csrf
                            @method('delete')
                            <button class="link link-danger" type="submit">{{ __('ui.devices.revoke') }}</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('tenant.admin.profile.devices.lock') }}" class="stack">
            @csrf
            @method('put')
            <input type="hidden" name="devices_locked" value="0">
            <label class="check"><input type="checkbox" name="devices_locked" value="1" @checked($user->devices_locked) onchange="this.form.submit()"> {{ __('ui.devices.lock') }}</label>
            <small class="row-note">{{ __('ui.devices.lock_hint') }}</small>
            <noscript><button class="btn btn-quiet" type="submit">{{ __('ui.common.save') }}</button></noscript>
        </form>
    </section>
    </div>
@endsection
