<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CustomerOffer;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tra cứu ưu đãi khi khách hỏi: chỉ tìm theo mã hoặc số điện thoại, không tự liệt kê hết
 * (danh sách đầy đủ để ở phần quản trị của chủ quán).
 */
class OfferController extends Controller
{
    public const STATUSES = ['unused', 'used', 'expired'];

    /** Nhiều nhất bao nhiêu kết quả một lần tìm. */
    private const LIMIT = 50;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $statuses = filter_values('status', self::STATUSES);

        return view('modules.dinein.offers', [
            'search' => $search,
            'statuses' => $statuses,
            'offers' => $search === '' ? null : $this->search($search, $statuses),
        ]);
    }

    public function destroy(CustomerOffer $offer): RedirectResponse
    {
        abort_if($offer->used_at !== null, 422);

        $offer->delete();
        ActivityLog::record($offer->restaurant_id, 'dinein', __('dinein.log_voucher_deleted', ['code' => $offer->code]), [$offer->summary()]);

        return back()->with('status', __('dinein.voucher_deleted', ['code' => $offer->code]));
    }

    /** @param  array<int, string>  $statuses */
    private function search(string $search, array $statuses)
    {
        $code = strtoupper(preg_replace('/\s+/', '', $search));
        $phone = Phone::normalize($search);

        return CustomerOffer::with(['customer', 'incident', 'creator', 'sourceTable', 'usedTable', 'usedOrder'])
            ->where(function (Builder $query) use ($code, $phone): void {
                $query->where('code', 'like', $code.'%');

                // Số điện thoại: từ 3 số trở lên, khớp một phần (khách có thể chỉ nhớ đuôi).
                if (strlen($phone) >= 3) {
                    $query->orWhereHas('customer', fn (Builder $customer) => $customer->where('phone', 'like', '%'.$phone.'%'));
                }
            })
            ->when($statuses !== [] && count($statuses) < count(self::STATUSES), fn (Builder $query) => $query->where(function (Builder $query) use ($statuses): void {
                foreach ($statuses as $status) {
                    $query->orWhere(fn (Builder $query) => match ($status) {
                        'used' => $query->whereNotNull('used_at'),
                        'expired' => $query->whereNull('used_at')->whereDate('expires_on', '<', today()),
                        default => $query->usable(),
                    });
                }
            }))
            ->latest('id')->limit(self::LIMIT)->get();
    }
}
