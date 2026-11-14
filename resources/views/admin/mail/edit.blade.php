@extends('layouts.admin')

@section('title', __("mail.templates.$key.name"))

@section('content')
    @include('partials.page-title', ['title' => __("mail.templates.$key.name"), 'back' => route('tenant.admin.mail.templates')])
    <p class="lead">{{ __("mail.templates.$key.hint") }}</p>

    <div class="mail-edit">
        <form method="post" action="{{ route('tenant.admin.mail.update', $key) }}" class="panel stack" data-guard data-mail-form>
            @csrf
            @method('put')
            <div class="field">
                <label for="mail-subject">{{ __('mail.subject') }}@include('partials.required')</label>
                <input id="mail-subject" name="subject" value="{{ old('subject', $template['subject']) }}" required maxlength="200" data-mail-subject>
                @error('subject')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <span class="field-label">{{ __('mail.body') }}@include('partials.required')</span>
                <div class="mail-editor" data-mail-editor>
                    <div class="mail-toolbar" role="toolbar">
                        <button type="button" class="row-btn" data-cmd="bold" aria-label="{{ __('mail.toolbar.bold') }}"><strong>B</strong></button>
                        <button type="button" class="row-btn" data-cmd="italic" aria-label="{{ __('mail.toolbar.italic') }}"><em>I</em></button>
                        <button type="button" class="row-btn" data-cmd="insertUnorderedList" aria-label="{{ __('mail.toolbar.list') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/></svg></button>
                        <button type="button" class="row-btn" data-cmd="createLink" data-prompt="{{ __('mail.toolbar.link_prompt') }}" aria-label="{{ __('mail.toolbar.link') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg></button>
                    </div>
                    <div class="mail-body" contenteditable="true" data-mail-body aria-label="{{ __('mail.body') }}">{!! $editorHtml !!}</div>
                    <textarea name="body" hidden data-mail-source>{{ $template['body'] }}</textarea>
                </div>
                @error('body')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="actions-row">
                <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
            </div>
        </form>

        <aside class="panel stack mail-vars">
            <div>
                <h2>{{ __('mail.variables_title') }}</h2>
                <small class="row-note">{{ __('mail.templates_lead') }}</small>
            </div>
            <div class="mail-var-list">
                @foreach ($variables as $variable)
                    <button type="button" class="mail-var" draggable="true" data-mail-var="{{ $variable }}">{{ __("mail.variables.$variable") }}</button>
                @endforeach
            </div>
        </aside>

        <section class="panel stack mail-preview">
            <div class="actions-row">
                <h2>{{ __('mail.preview') }}</h2>
                @if ($template['custom'])
                    <form method="post" action="{{ route('tenant.admin.mail.reset', $key) }}" data-confirm="{{ __('mail.reset_confirm') }}" data-confirm-title="{{ __('mail.reset') }}" data-confirm-ok="{{ __('mail.reset') }}" data-confirm-danger>
                        @csrf
                        @method('delete')
                        <button class="link link-danger" type="submit">{{ __('mail.reset') }}</button>
                    </form>
                @endif
            </div>
            <p class="mail-preview-subject"><strong>{{ $preview['subject'] }}</strong></p>
            <iframe class="mail-frame" title="{{ __('mail.preview') }}" sandbox srcdoc="{{ $preview['html'] }}"></iframe>
            <form method="post" action="{{ route('tenant.admin.mail.template.test', $key) }}" class="copy-row">
                @csrf
                <input name="to" type="email" required value="{{ auth()->user()->email }}" aria-label="{{ __('mail.test_to') }}">
                <button class="btn btn-quiet" type="submit">{{ __('mail.test_send') }}</button>
            </form>
        </section>
    </div>
@endsection
