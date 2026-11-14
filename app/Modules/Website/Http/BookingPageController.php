<?php

namespace App\Modules\Website\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\ModuleManager;
use App\Modules\Website\BookingPages;
use App\Modules\Website\Models\BookingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Website → Web đặt trước: chọn trang mẫu hay trang tự tạo; trang tự tạo đi theo
 * bản nháp → kiểm tra → xem thử, gửi thử → đăng. Luôn giữ các bản đã đăng để lấy lại.
 */
class BookingPageController extends Controller
{
    public function __construct(private ModuleManager $modules) {}

    public function index(): View|RedirectResponse
    {
        if (! has_module('reservation')) {
            return view('admin.module-placeholder', ['module' => 'website']);
        }

        return redirect()->route('tenant.admin.website.booking');
    }

    public function show(): View
    {
        return view('modules.website.booking', [
            'mode' => BookingPages::mode(),
            'draft' => BookingPage::draft()?->load('creator'),
            'live' => BookingPage::published()?->load('creator'),
            'versions' => BookingPage::where('status', BookingPage::ARCHIVED)->with('creator')->latest('published_at')->limit(10)->get(),
            'prompt' => BookingPages::prompt(),
            'link' => tenant_route('public.booking'),
            'online' => (bool) $this->modules->settings(tenant(), 'reservation')['online_enabled'],
        ]);
    }

    public function mode(Request $request): RedirectResponse
    {
        $mode = $request->validate(['mode' => ['required', Rule::in(['default', 'custom'])]])['mode'];

        if ($mode === 'custom' && BookingPage::published() === null) {
            return back()->with('error', __('website.admin.mode_custom_none'));
        }

        $this->modules->updateSettings(tenant(), 'website', ['booking_page' => $mode]);
        ActivityLog::record(tenant()->id, 'settings', __('website.admin.log_mode', ['mode' => __('website.admin.mode_'.$mode)]));

        return back()->with('status', __('website.admin.mode_saved'));
    }

    /** Tải lên (hoặc dán) trang mới: thành bản nháp mới, kiểm tra ngay, chưa gửi thử. */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:1000', 'mimetypes:text/html,text/plain,application/xhtml+xml'],
            'html' => ['nullable', 'string', 'max:'.BookingPages::MAX_BYTES],
        ]);

        $html = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : trim((string) $request->input('html'));

        if ($html === '') {
            return back()->withErrors(['file' => __('website.admin.upload_needed')]);
        }

        DB::transaction(function () use ($request, $html) {
            BookingPage::where('status', BookingPage::DRAFT)->delete();
            BookingPage::create([
                'status' => BookingPage::DRAFT,
                'file_name' => $request->hasFile('file') ? mb_substr($request->file('file')->getClientOriginalName(), 0, 200) : null,
                'html' => $html,
                'checks' => BookingPages::check($html),
                'created_by' => $request->user()->id,
            ]);
        });

        ActivityLog::record(tenant()->id, 'settings', __('website.admin.log_upload'));

        return back()->with('status', __('website.admin.uploaded'));
    }

    /** Mở bản nháp ở chế độ thử (link có chữ ký, 2 giờ). */
    public function preview(): RedirectResponse
    {
        $draft = BookingPage::draft();
        abort_if($draft === null || $draft->hasErrors(), 404);

        // Ký đúng địa chỉ khách thấy (tên miền riêng nếu có), không thì chuyển hướng làm hỏng chữ ký.
        $domain = tenant()->primaryDomain();
        $url = $domain
            ? URL::temporarySignedRoute('domain.public.booking.preview', now()->addHours(2), ['customDomain' => $domain, 'page' => $draft->id])
            : URL::temporarySignedRoute('tenant.public.booking.preview', now()->addHours(2), ['resto' => tenant()->slug, 'page' => $draft->id]);

        return redirect()->away($url);
    }

    public function publish(): RedirectResponse
    {
        $draft = BookingPage::draft();

        if ($draft === null || ! $draft->canPublish()) {
            return back()->with('error', __('website.admin.cannot_publish'));
        }

        DB::transaction(function () use ($draft) {
            BookingPage::where('status', BookingPage::PUBLISHED)->update(['status' => BookingPage::ARCHIVED]);
            $draft->update(['status' => BookingPage::PUBLISHED, 'published_at' => now()]);
        });

        $this->modules->updateSettings(tenant(), 'website', ['booking_page' => 'custom']);
        ActivityLog::record(tenant()->id, 'settings', __('website.admin.log_publish'));

        return back()->with('status', __('website.admin.published'));
    }

    public function discard(): RedirectResponse
    {
        BookingPage::where('status', BookingPage::DRAFT)->delete();
        ActivityLog::record(tenant()->id, 'settings', __('website.admin.log_discard'));

        return back()->with('status', __('website.admin.discarded'));
    }

    /** Lấy bản đã đăng (đang chạy hoặc bản cũ) làm bản nháp mới, phải thử lại mới đăng. */
    public function restore(BookingPage $page): RedirectResponse
    {
        abort_if($page->status === BookingPage::DRAFT, 404);

        DB::transaction(function () use ($page) {
            BookingPage::where('status', BookingPage::DRAFT)->delete();
            BookingPage::create([
                'status' => BookingPage::DRAFT,
                'file_name' => $page->file_name,
                'html' => $page->html,
                'checks' => BookingPages::check($page->html),
                'created_by' => auth()->id(),
            ]);
        });

        return back()->with('status', __('website.admin.restored'));
    }
}
