<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Quán chưa có trang chủ (chưa làm) mà có đặt bàn online: vào thẳng trang đặt bàn,
     * ví dụ /saigon → /saigon/dat-ban. Giữ nguyên query (utm...). Không có đặt bàn thì trang tạm.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! self::hasHomepage() && has_module('reservation')) {
            $query = $request->getQueryString();

            return redirect()->to(tenant_route('public.booking').($query ? '?'.$query : ''));
        }

        return view('public.home', ['restaurant' => tenant()]);
    }

    /** Trang chủ của quán (chưa có, sẽ làm ở module Website). */
    public static function hasHomepage(): bool
    {
        return false;
    }
}
