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
            // Đổi sang khách khác thì ưu đãi của khách cũ đang áp được trả lại.
            $this->releaseOffer($table);
            $customer = Customer::remember($data['name'], Phone::normalize($data['phone']));
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
            $this->releaseOffer($table);
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

    /** Tặng ưu đãi cho lần sau khách quay lại (ví dụ bù sự cố hôm nay). */
    public function gift(Request $request, DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen() && $table->customer_id, 404);

        $data = $request->validateWithBag('gift-dialog', [
            'type' => ['required', Rule::enum(DiscountType::class)],
            'percent' => ['nullable', 'required_if:type,percent', 'integer', 'min:1', 'max:100'],
            'thousands' => ['nullable', 'required_if:type,amount', 'integer', 'min:1', 'max:999999'],
            'reason' => ['required', 'string', 'max:300'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);
        $type = DiscountType::from($data['type']);

        $offer = CustomerOffer::create([
            'customer_id' => $table->customer_id,
            'type' => $type,
            'value' => $type === DiscountType::Percent ? (int) $data['percent'] : (int) $data['thousands'] * 1000,
            'reason' => $data['reason'],
            'expires_on' => $data['expires_on'] ?? null,
            'dinein_table_id' => $table->id,
            'created_by' => $request->user()->id,
        ]);
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_gift', ['table' => $table->label, 'name' => $table->customer->name]), [$offer->summary()], $table);

        return back()->with('status', __('dinein.gift_saved', ['name' => $table->customer->name]));
    }

    /** Áp ưu đãi của khách đang gắn vào phiếu tạm tính (mỗi bàn một mức giảm, ưu đãi dùng một lần). */
    public function apply(DiningTable $table, CustomerOffer $offer): RedirectResponse
    {
        abort_unless($table->isOpen() && $offer->customer_id === $table->customer_id, 404);

        if ($offer->used_at !== null || ($offer->expires_on && $offer->expires_on->isBefore(today())) || $table->discount_type !== null) {
            return back()->with('error', __('dinein.offer_unavailable'));
        }

        DB::transaction(function () use ($table, $offer): void {
            $offer->update(['used_at' => now(), 'used_table_id' => $table->id]);
            $table->update(['discount_type' => $offer->type, 'discount_value' => $offer->value, 'customer_offer_id' => $offer->id]);
        });
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_offer_applied', ['table' => $table->label]), [$offer->summary()], $table);

        return back()->with('status', __('dinein.offer_applied'));
    }

    /** Bỏ giảm giá trên phiếu; ưu đãi trả lại cho khách dùng lần khác. */
    public function removeDiscount(DiningTable $table): RedirectResponse
    {
        abort_unless($table->isOpen() && $table->discount_type !== null, 404);

        DB::transaction(fn () => $this->releaseOffer($table));
        ActivityLog::record($table->restaurant_id, 'dinein', __('dinein.log_discount_removed', ['table' => $table->label]), [], $table);

        return back()->with('status', __('dinein.discount_removed'));
    }

    private function releaseOffer(DiningTable $table): void
    {
        $table->offer?->update(['used_at' => null, 'used_table_id' => null]);
        $table->update(['discount_type' => null, 'discount_value' => null, 'customer_offer_id' => null]);
    }
}
