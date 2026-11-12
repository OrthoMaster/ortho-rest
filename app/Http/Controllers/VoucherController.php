<?php

namespace App\Http\Controllers;

use App\Models\CustomerOffer;
use Illuminate\View\View;

/** Trang ưu đãi của khách (mở bằng mã QR): mức giảm, mã, hạn dùng, lưu thành hình. */
class VoucherController extends Controller
{
    public function __invoke(string $token): View
    {
        $offer = CustomerOffer::where('token', $token)->firstOrFail();

        return view('public.voucher', ['offer' => $offer]);
    }
}
