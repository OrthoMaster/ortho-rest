<?php

namespace App\Modules\DineIn\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Modules\DineIn\Models\DiningTable;
use App\Modules\DineIn\Models\Incident;
use App\Modules\Takeaway\DiscountType;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Khách và sự cố của một bàn đang có khách: gắn khách theo số điện thoại, ghi sự cố,
 * tặng ưu đãi lần sau, áp ưu đãi của khách vào phiếu tạm tính.
 */
class GuestController extends Controller
{
    /** Gắn khách theo số điện thoại; khách đã có thì nhận ra luôn (lần sau tra theo số này). */
    public function attach(Request $request, DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen(), 404);

        $data = $request->validateWithBag('guest-dialog', [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', Phone::RULE],
        ]);

        DB::transaction(function () use ($table, $data): void {
            // Đổi sang khách khác thì ưu đãi của khách cũ đang áp được trả lại (voucher không tên thì giữ).
            $customer = Customer::remember($data['name'], Phone::normalize($data['phone']));
            $this->releaseCustomerOffer($table, $customer->id);
            $table->update(['customer_id' => $customer->id]);
        });

        $table->load('customer');
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_guest', ['table' => $table->label, 'name' => $table->customer->name, 'phone' => mask_phone($table->customer->phone)]), [], $table);

        return back()->with('status', __('dinein.guest_saved', ['name' => $table->customer->name]));
    }

    public function detach(DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen() && $table->customer_id, 404);

        $name = $table->customer->name;
        DB::transaction(function () use ($table): void {
            $this->releaseCustomerOffer($table);
            $table->update(['customer_id' => null]);
        });
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_guest_removed', ['table' => $table->label, 'name' => $name]), [], $table);

        return back()->with('status', __('dinein.guest_removed'));
    }

    public function incident(Request $request, DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen(), 404);

        $data = $request->validateWithBag('incident-dialog', [
            'kind' => ['required', Rule::in(Incident::KINDS)],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $incident = $table->incidents()->create([...$data, 'restaurant_id' => $table->restaurant_id, 'created_by' => $request->user()->id]);
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_incident', ['table' => $table->label, 'kind' => $incident->kindLabel()]), [$incident->note], $table);

        return back()->with('status', __('dinein.incident_saved'));
    }

    /**
     * Tặng ưu đãi cho lần sau khách quay lại (ví dụ bù sự cố hôm nay). Bàn có gắn khách thì mặc định
     * tặng cho khách đó; không thì là voucher không tên, khách quét mã QR để lưu trên điện thoại.
     */
    public function gift(Request $request, DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen(), 404);

        $data = $request->validateWithBag('gift-dialog', [
            'type' => ['required', Rule::enum(DiscountType::class)],
            'percent' => ['nullable', 'required_if:type,percent', 'integer', 'min:1', 'max:100'],
            'thousands' => ['nullable', 'required_if:type,amount', 'integer', 'min:1', 'max:999999'],
            'reason' => ['required', 'string', 'max:300'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);
        $type = DiscountType::from($data['type']);
        $customer = $table->customer_id && $request->boolean('for_customer') ? $table->customer : null;

        $offer = CustomerOffer::create([
            'customer_id' => $customer?->id,
            'type' => $type,
            'value' => $type === DiscountType::Percent ? (int) $data['percent'] : (int) $data['thousands'] * 1000,
            'reason' => $data['reason'],
            'expires_on' => $data['expires_on'] ?? null,
            'dinein_table_id' => $table->id,
            'created_by' => $request->user()->id,
        ]);
        ActivityLog::record($table->restaurant_id, 'dinein', $customer
            ? __('dinein.log_gift', ['table' => $table->label, 'name' => $customer->name])
            : __('dinein.log_voucher', ['table' => $table->label, 'code' => $offer->code]), [$offer->summary()], $table);

        // Mở luôn mã QR để khách quét lưu về điện thoại.
        return back()->with('status', $customer ? __('dinein.gift_saved', ['name' => $customer->name]) : __('dinein.voucher_saved'))
            ->with('voucher', $offer->id);
    }

    /** Áp ưu đãi của khách đang gắn vào phiếu tạm tính (mỗi bàn một mức giảm, ưu đãi dùng một lần). */
    public function apply(DiningTable $table, CustomerOffer $offer): RedirectResponse
    {
        abort_unless($table->isOpen() && $offer->customer_id === $table->customer_id, 404);

        return $this->applyOffer($table, $offer);
    }

    /** Áp ưu đãi theo mã khách đưa (voucher không tên, hoặc mã của khách khác như người nhà). */
    public function applyCode(Request $request, DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen(), 404);

        $code = $request->validateWithBag('voucher', ['code' => ['required', 'string', 'max:20']])['code'];
        $offer = CustomerOffer::usableCode($code);

        if ($offer === null) {
            return back()->withErrors(['code' => __('dinein.voucher_invalid')], 'voucher')->withInput();
        }

        return $this->applyOffer($table, $offer);
    }

    /** Bỏ giảm giá trên phiếu; ưu đãi trả lại cho khách dùng lần khác. */
    public function removeDiscount(DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen() && $table->discount_type !== null, 404);

        DB::transaction(fn () => $this->releaseOffer($table));
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_discount_removed', ['table' => $table->label]), [], $table);

        return back()->with('status', __('dinein.discount_removed'));
    }

    private function applyOffer(DiningTable $table, CustomerOffer $offer): RedirectResponse
    {
        if (! $offer->isUsable() || $table->discount_type !== null) {
            return back()->with('error', __('dinein.offer_unavailable'));
        }

        DB::transaction(function () use ($table, $offer): void {
            $offer->markUsed($table);
            $table->update(['discount_type' => $offer->type, 'discount_value' => $offer->value, 'customer_offer_id' => $offer->id]);
        });
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_offer_applied', ['table' => $table->label]), [$offer->code.' · '.$offer->summary()], $table);

        return back()->with('status', __('dinein.offer_applied'));
    }

    /** Ưu đãi riêng của khách đang áp trên phiếu được trả lại khi bỏ hoặc đổi khách. */
    private function releaseCustomerOffer(DiningTable $table, ?int $keep = null): void
    {
        $owner = $table->offer?->customer_id;

        if ($owner !== null && $owner !== $keep) {
            $this->releaseOffer($table);
        }
    }

    private function releaseOffer(DiningTable $table): void
    {
        $table->offer?->release();
        $table->update(['discount_type' => null, 'discount_value' => null, 'customer_offer_id' => null]);
    }
}
