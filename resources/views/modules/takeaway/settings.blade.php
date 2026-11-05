@extends('layouts.admin')

@section('title', __('takeaway.settings'))

@section('content')
    <h1>{{ __('modules.takeaway') }}</h1>

    @include('modules.takeaway.subnav')

    <form method="post" action="{{ route('tenant.admin.takeaway.settings.update') }}" class="panel narrow stack" data-guard>
        @csrf
        @method('put')
        <label class="check"><input type="checkbox" name="accepting_orders" value="1" @checked($settings['accepting_orders'])> {{ __('takeaway.accepting_orders') }}</label>
        <div class="form-row">
            @include('partials.field', ['name' => 'pickup_opens', 'type' => 'time', 'label' => __('takeaway.pickup_opens'), 'value' => $settings['pickup_opens']])
            @include('partials.field', ['name' => 'pickup_closes', 'type' => 'time', 'label' => __('takeaway.pickup_closes'), 'value' => $settings['pickup_closes']])
        </div>
        @if (auth()->user()->role === \App\Enums\UserRole::Owner)
            <div class="field">
                <span class="field-label">{{ __('takeaway.direct_discount_permission') }}</span>
                <small>{{ __('takeaway.direct_discount_hint') }}</small>
                <div class="toggles">
                    @foreach ([\App\Enums\UserRole::Manager, \App\Enums\UserRole::Staff] as $role)
                        <label class="check"><input type="checkbox" name="direct_discount_roles[]" value="{{ $role->value }}" @checked(in_array($role->value, $settings['direct_discount_roles'], true))> {{ $role->label() }}</label>
                    @endforeach
                </div>
            </div>
        @endif
        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
    </form>
@endsection
