<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CustomerOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Ưu đãi lần sau đã tặng: xem lại mã QR, xem đã dùng chưa, huỷ mã chưa dùng. */
class OfferController extends Controller
{
    public function index(): View
    {
        return view('modules.dinein.offers', [
            'offers' => CustomerOffer::with(['customer', 'creator', 'sourceTable', 'usedTable', 'usedOrder'])->latest('id')->paginate(30),
        ]);
    }

    public function destroy(CustomerOffer $offer): RedirectResponse
    {
        abort_if($offer->used_at !== null, 422);

        $offer->delete();
        ActivityLog::record($offer->restaurant_id, 'dinein', __('dinein.log_voucher_deleted', ['code' => $offer->code]), [$offer->summary()]);

        return back()->with('status', __('dinein.voucher_deleted', ['code' => $offer->code]));
    }
}
