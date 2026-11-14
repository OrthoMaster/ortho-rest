@extends('layouts.admin')

@section('title', __('mail.title'))

@section('content')
    <h1>{{ __('mail.title') }}</h1>
    @include('admin.mail.subnav')

    <form method="post" action="{{ route('tenant.admin.mail.server.update') }}" class="cols cols-2" data-guard>
        @csrf
        @method('put')
        <section class="panel stack">
            <div>
                <h2>{{ __('mail.server_title') }}</h2>
                <small class="row-note">{{ __('mail.server_lead') }}</small>
            </div>
            <p class="badge {{ $settings->usesSmtp() ? 'badge-ok' : 'badge-muted' }}">{{ $settings->usesSmtp() ? __('mail.via_smtp', ['host' => $settings->smtp_host]) : __('mail.via_system') }}</p>
            <label class="check">
                <input type="checkbox" name="use_smtp" value="1" data-reveal="smtp" @checked(old('use_smtp', $settings->use_smtp))>
                {{ __('mail.use_smtp') }}
            </label>
            <div class="stack" data-reveal-for="smtp" @unless (old('use_smtp', $settings->use_smtp)) hidden @endunless>
                @include('partials.field', ['name' => 'smtp_host', 'label' => __('mail.smtp_host'), 'value' => $settings->smtp_host, 'required' => false])
                <div class="cols cols-2">
                    @include('partials.field', ['name' => 'smtp_port', 'label' => __('mail.smtp_port'), 'type' => 'number', 'value' => $settings->smtp_port ?? 587, 'required' => false, 'min' => 1, 'max' => 65535])
                    @include('partials.select', ['name' => 'smtp_encryption', 'label' => __('mail.smtp_encryption'), 'options' => __('mail.encryptions'), 'value' => $settings->smtp_encryption ?? 'tls', 'required' => false])
                </div>
                @include('partials.field', ['name' => 'smtp_username', 'label' => __('mail.smtp_username'), 'value' => $settings->smtp_username, 'required' => false, 'autocomplete' => 'off'])
                @include('partials.field', ['name' => 'smtp_password', 'label' => __('mail.smtp_password'), 'type' => 'password', 'required' => false, 'autocomplete' => 'new-password', 'hint' => $settings->smtp_password ? __('mail.smtp_password_keep') : null])
                @include('partials.field', ['name' => 'from_address', 'label' => __('mail.from_address'), 'type' => 'email', 'value' => $settings->from_address, 'required' => false])
            </div>
            @include('partials.field', ['name' => 'from_name', 'label' => __('mail.from_name'), 'value' => $settings->from_name, 'required' => false, 'hint' => __('mail.from_name_hint')])
        </section>

        <div class="stack">
            <section class="panel stack">
                <div>
                    <h2>{{ __('mail.notify_title') }}</h2>
                    <small class="row-note">{{ __('mail.notify_lead') }}</small>
                </div>
                @include('partials.textarea', ['name' => 'notify_emails', 'label' => __('mail.notify_emails'), 'rows' => 4, 'value' => implode("\n", $settings->notify_emails ?? []), 'placeholder' => implode("\n", $staffEmails)])
            </section>
            <div><button class="btn" type="submit">{{ __('ui.common.save') }}</button></div>
        </div>
    </form>

    <section class="panel stack w-md mail-test">
        <h2>{{ __('mail.test_title') }}</h2>
        <form method="post" action="{{ route('tenant.admin.mail.test') }}" class="copy-row">
            @csrf
            <input name="to" type="email" required value="{{ auth()->user()->email }}" aria-label="{{ __('mail.test_to') }}">
            <button class="btn btn-quiet" type="submit">{{ __('mail.test_send') }}</button>
        </form>
    </section>
@endsection
