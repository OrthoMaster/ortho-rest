@extends('layouts.admin')

@section('title', __('mail.tabs.templates'))

@section('content')
    <h1>{{ __('mail.title') }}</h1>
    @include('admin.mail.subnav')

    <section class="panel w-lg">
        <ul class="rows">
            @foreach ($keys as $key)
                <li class="row-link">
                    <span class="row-main">
                        <a href="{{ route('tenant.admin.mail.edit', $key) }}">{{ __("mail.templates.$key.name") }}</a>
                        <small class="row-note">{{ __("mail.templates.$key.hint") }}</small>
                    </span>
                    <span class="badge {{ in_array($key, $custom, true) ? 'badge-ok' : 'badge-muted' }}">{{ in_array($key, $custom, true) ? __('mail.custom') : __('mail.default') }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
