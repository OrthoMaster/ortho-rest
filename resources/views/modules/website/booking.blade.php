@extends('layouts.admin')

@section('title', __('website.tabs.booking'))

@section('content')
    <h1>{{ __('website.title') }}</h1>
    @include('modules.website.subnav')

    <p class="lead">{{ __('website.admin.lead') }}</p>
    @unless ($online)
        <p class="flash flash-warn" role="status">{{ __('website.admin.reservation_off') }}</p>
    @endunless

    <div class="cols cols-2">
        <section class="panel stack">
            <h2>{{ __('website.admin.link') }}</h2>
            <div class="copy-row">
                <input id="booking-link" value="{{ $link }}" readonly aria-label="{{ __('website.admin.link') }}">
                <button class="btn btn-quiet" type="button" data-copy="#booking-link" data-copied="{{ __('website.admin.copied') }}">{{ __('website.admin.copy') }}</button>
            </div>
            <p><a href="{{ $link }}" target="_blank" rel="noopener">{{ __('website.admin.open') }} ↗</a></p>
        </section>

        <section class="panel">
            <form method="post" action="{{ route('tenant.admin.website.booking.mode') }}" class="stack" data-guard>
                @csrf
                @method('put')
                <h2>{{ __('website.admin.mode_title') }}</h2>
                <div class="choice-list">
                <label class="choice">
                    <input type="radio" name="mode" value="default" @checked($mode === 'default' || ! $live)>
                    <span><strong>{{ __('website.admin.mode_default') }}</strong><br><small class="row-note">{{ __('website.admin.mode_default_hint') }}</small></span>
                    <a class="btn btn-quiet btn-sm" href="{{ tenant_route('public.booking.default') }}" target="_blank" rel="noopener">{{ __('website.admin.preview_default') }}</a>
                </label>
                <label class="choice">
                    <input type="radio" name="mode" value="custom" @checked($mode === 'custom' && $live) @disabled(! $live)>
                    <span><strong>{{ __('website.admin.mode_custom') }}</strong><br><small class="row-note">{{ $live ? __('website.admin.mode_custom_hint') : __('website.admin.mode_custom_none') }}</small></span>
                </label>
                </div>
                <div><button class="btn" type="submit">{{ __('ui.common.save') }}</button></div>
            </form>
        </section>

        {{-- Tự tạo bằng AI: prompt → tải lên → kiểm tra → xem thử, gửi thử → đăng. --}}
        <section class="panel span-all">
            <div class="panel-split">
                <div class="stack">
                    <h2>{{ __('website.admin.steps_title') }}</h2>
                    <ol class="steps">
                        <li>{{ __('website.admin.step_prompt') }}</li>
                        <li>{{ __('website.admin.step_upload') }}</li>
                        <li>{{ __('website.admin.step_test') }}</li>
                        <li>{{ __('website.admin.step_publish') }}</li>
                    </ol>
                    <div class="field">
                        <span class="field-label">{{ __('website.admin.prompt_title') }}</span>
                        <textarea id="booking-prompt" class="prompt-box" rows="10" readonly>{{ $prompt }}</textarea>
                    </div>
                    <div><button class="btn btn-quiet" type="button" data-copy="#booking-prompt" data-copied="{{ __('website.admin.copied') }}">{{ __('website.admin.copy_prompt') }}</button></div>
                </div>

                <form method="post" action="{{ route('tenant.admin.website.booking.upload') }}" enctype="multipart/form-data" class="stack">
                    @csrf
                    <h2>{{ __('website.admin.upload_title') }}</h2>
                    <div class="field">
                        <label for="booking-file">{{ __('website.admin.upload_file') }}</label>
                        <input id="booking-file" name="file" type="file" accept=".html,.htm,text/html">
                        @error('file')<small class="error">{{ $message }}</small>@enderror
                    </div>
                    @include('partials.textarea', ['name' => 'html', 'label' => __('website.admin.or_paste'), 'rows' => 8, 'placeholder' => __('website.admin.paste_placeholder')])
                    <div><button class="btn" type="submit">{{ __('website.admin.upload') }}</button></div>
                </form>
            </div>
        </section>

        <section class="panel stack">
            <h2>{{ __('website.admin.draft_title') }}</h2>
            @if ($draft)
                <small class="row-note">{{ __('website.admin.draft_meta', ['file' => $draft->file_name ?: 'HTML', 'time' => $draft->created_at->format('H:i d/m'), 'user' => $draft->creator?->name ?? '—']) }}</small>
                <ul class="checks" aria-label="{{ __('website.admin.checks') }}">
                    @foreach ($draft->checks as $check)
                        <li class="is-{{ $check['level'] }}"><span aria-hidden="true">{{ ['ok' => '✓', 'warn' => '!', 'error' => '✕'][$check['level']] }}</span> {{ $check['text'] }}</li>
                    @endforeach
                </ul>
                @if ($draft->hasErrors())
                    <p class="flash flash-error" role="alert">{{ __('website.admin.has_errors') }}</p>
                @elseif ($draft->tested_at)
                    <p class="flash" role="status">{{ __('website.admin.tested', ['time' => $draft->tested_at->format('H:i d/m')]) }}</p>
                @else
                    <p class="flash flash-warn" role="status">{{ __('website.admin.not_tested') }}</p>
                @endif
                <div class="actions-row">
                    @unless ($draft->hasErrors())
                        <a class="btn btn-quiet" href="{{ route('tenant.admin.website.booking.preview') }}" target="_blank" rel="noopener">{{ __('website.admin.preview') }} ↗</a>
                    @endunless
                    <form method="post" action="{{ route('tenant.admin.website.booking.publish') }}"
                          data-confirm="{{ __('website.admin.publish_confirm') }}" data-confirm-title="{{ __('website.admin.publish_title') }}" data-confirm-ok="{{ __('website.admin.publish') }}">
                        @csrf
                        <button class="btn" type="submit" @disabled(! $draft->canPublish())>{{ __('website.admin.publish') }}</button>
                    </form>
                    <form method="post" action="{{ route('tenant.admin.website.booking.discard') }}"
                          data-confirm="{{ __('website.admin.discard_confirm') }}" data-confirm-title="{{ __('website.admin.discard_title') }}" data-confirm-ok="{{ __('website.admin.discard') }}" data-confirm-danger>
                        @csrf
                        @method('delete')
                        <button class="link link-danger" type="submit">{{ __('website.admin.discard') }}</button>
                    </form>
                </div>
            @else
                <p class="row-note">{{ __('website.admin.no_draft') }}</p>
            @endif
        </section>

        <section class="panel stack">
            <h2>{{ __('website.admin.live_title') }}</h2>
            @if ($live)
                <ul class="rows">
                    <li>
                        <span class="row-main">
                            <strong>{{ $live->file_name ?: 'HTML' }}</strong>
                            <small class="row-note">{{ __('website.admin.live_meta', ['time' => $live->published_at->format('H:i d/m/Y'), 'user' => $live->creator?->name ?? '—']) }}</small>
                        </span>
                        @if ($mode === 'custom')<span class="badge badge-ok">{{ __('website.admin.mode_custom') }}</span>@endif
                    </li>
                </ul>
            @else
                <p class="row-note">{{ __('website.admin.mode_custom_none') }}</p>
            @endif

            <h3>{{ __('website.admin.versions_title') }}</h3>
            @forelse ($versions as $version)
                @if ($loop->first)<ul class="rows">@endif
                <li>
                    <span class="row-main">
                        <span>{{ $version->file_name ?: 'HTML' }}</span>
                        <small class="row-note">{{ __('website.admin.live_meta', ['time' => $version->published_at?->format('H:i d/m/Y') ?? '—', 'user' => $version->creator?->name ?? '—']) }}</small>
                    </span>
                    <form method="post" action="{{ route('tenant.admin.website.booking.restore', $version) }}">
                        @csrf
                        <button class="btn btn-quiet btn-sm" type="submit">{{ __('website.admin.restore') }}</button>
                    </form>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="row-note">{{ __('website.admin.no_versions') }}</p>
            @endforelse
        </section>
    </div>
@endsection
