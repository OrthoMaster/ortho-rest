<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RestaurantMail;
use App\Models\ActivityLog;
use App\Models\MailSetting;
use App\Models\MailTemplate;
use App\Support\SafeHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Email của quán: máy chủ gửi (SMTP riêng hoặc email hệ thống), email nhận thông
 * báo, và mẫu email gửi khách / gửi quán (trình soạn có biến kéo thả).
 */
class MailController extends Controller
{
    public function server(): View
    {
        return view('admin.mail.server', ['settings' => MailSetting::current(), 'staffEmails' => RestaurantMail::staffEmails()]);
    }

    public function updateServer(Request $request): RedirectResponse
    {
        $smtp = $request->boolean('use_smtp');
        $data = $request->validate([
            'smtp_host' => [$smtp ? 'required' : 'nullable', 'string', 'max:255'],
            'smtp_port' => [$smtp ? 'required' : 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'from_address' => [$smtp ? 'required' : 'nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'notify_emails' => ['nullable', 'string', 'max:2000'],
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['notify_emails'] ?? '')))->filter()->unique()->values();
        $bad = $emails->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($bad->isNotEmpty()) {
            return back()->withInput()->withErrors(['notify_emails' => __('validation.email', ['attribute' => $bad->implode(', ')])]);
        }

        $settings = MailSetting::current();
        $settings->fill([
            'use_smtp' => $smtp,
            'smtp_host' => $data['smtp_host'] ?? null,
            'smtp_port' => $data['smtp_port'] ?? null,
            'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            'smtp_username' => $data['smtp_username'] ?? null,
            'from_address' => $data['from_address'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'notify_emails' => $emails->all() ?: null,
        ]);

        if (filled($data['smtp_password'] ?? null)) {
            $settings->smtp_password = $data['smtp_password'];
        }

        $settings->save();
        ActivityLog::record(tenant()->id, 'settings', __('mail.log_settings'));

        return back()->with('status', __('mail.saved'));
    }

    /** Gửi một email thử bằng cài đặt đã lưu, báo lỗi máy chủ nếu có. */
    public function test(Request $request): RedirectResponse
    {
        $to = $request->validate(['to' => ['required', 'email']])['to'];
        $mail = RestaurantMail::render(
            ['subject' => __('mail.test_subject', ['name' => tenant()->name]), 'body' => __('mail.test_body')],
            RestaurantMail::sampleValues(),
        );

        try {
            RestaurantMail::deliver(tenant(), [$to], $mail, 'test', throw: true);
        } catch (Throwable $exception) {
            return back()->with('error', __('mail.test_failed', ['error' => mb_strimwidth($exception->getMessage(), 0, 200, '…')]));
        }

        return back()->with('status', __('mail.test_sent', ['email' => $to]));
    }

    public function templates(): View
    {
        $custom = MailTemplate::pluck('key')->all();

        return view('admin.mail.templates', ['keys' => array_keys(RestaurantMail::TEMPLATES), 'custom' => $custom]);
    }

    public function edit(string $key): View
    {
        abort_unless(isset(RestaurantMail::TEMPLATES[$key]), 404);
        $template = RestaurantMail::template($key);

        return view('admin.mail.edit', [
            'key' => $key,
            'template' => $template,
            'variables' => RestaurantMail::variables($key),
            'editorHtml' => $this->editorHtml($template['body']),
            'preview' => RestaurantMail::render($template, RestaurantMail::sampleValues()),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(isset(RestaurantMail::TEMPLATES[$key]), 404);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        MailTemplate::updateOrCreate(['key' => $key], ['subject' => trim($data['subject']), 'body' => SafeHtml::clean($data['body'])]);
        ActivityLog::record(tenant()->id, 'settings', __('mail.log_template', ['name' => __("mail.templates.$key.name")]));

        return redirect()->route('tenant.admin.mail.edit', $key)->with('status', __('mail.template_saved'));
    }

    public function reset(string $key): RedirectResponse
    {
        abort_unless(isset(RestaurantMail::TEMPLATES[$key]), 404);
        MailTemplate::where('key', $key)->delete();
        ActivityLog::record(tenant()->id, 'settings', __('mail.log_template_reset', ['name' => __("mail.templates.$key.name")]));

        return redirect()->route('tenant.admin.mail.edit', $key)->with('status', __('mail.reset_done'));
    }

    /** Gửi thử mẫu này (bản đã lưu, dữ liệu mẫu) tới email của người đang đăng nhập. */
    public function testTemplate(Request $request, string $key): RedirectResponse
    {
        abort_unless(isset(RestaurantMail::TEMPLATES[$key]), 404);
        $to = $request->validate(['to' => ['required', 'email']])['to'];

        try {
            RestaurantMail::deliver(tenant(), [$to], RestaurantMail::render(RestaurantMail::template($key), RestaurantMail::sampleValues()), $key, throw: true);
        } catch (Throwable $exception) {
            return back()->with('error', __('mail.test_failed', ['error' => mb_strimwidth($exception->getMessage(), 0, 200, '…')]));
        }

        return back()->with('status', __('mail.test_sent', ['email' => $to]));
    }

    /** Biến {{...}} trong nội dung thành ô biến trong trình soạn. */
    private function editorHtml(string $body): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($match) {
            $label = __("mail.variables.{$match[1]}");

            return is_string($label) && $label !== "mail.variables.{$match[1]}"
                ? '<span class="mail-chip" contenteditable="false" data-var="'.$match[1].'">'.e($label).'</span>'
                : $match[0];
        }, SafeHtml::clean($body));
    }
}
