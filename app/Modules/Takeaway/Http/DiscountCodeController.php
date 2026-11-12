<?php

namespace App\Modules\Takeaway\Http;

use App\Http\Controllers\Controller;
use App\Models\CustomerOffer;
use App\Modules\Takeaway\DiscountType;
use App\Modules\Takeaway\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiscountCodeController extends Controller
{
    public function index(): View
    {
        return view('modules.takeaway.discounts', ['codes' => DiscountCode::orderBy('code')->get()]);
    }

    public function create(): View
    {
        return view('modules.takeaway.discount-form', [
            'discount' => new DiscountCode(['type' => DiscountType::Percent, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DiscountCode::create($this->validated($request));

        return redirect()->route('tenant.admin.takeaway.discounts.index')->with('status', __('takeaway.code_saved'));
    }

    public function edit(DiscountCode $discount): View
    {
        return view('modules.takeaway.discount-form', compact('discount'));
    }

    public function update(Request $request, DiscountCode $discount): RedirectResponse
    {
        $discount->update($this->validated($request, $discount));

        return redirect()->route('tenant.admin.takeaway.discounts.index')->with('status', __('takeaway.code_saved'));
    }

    public function destroy(DiscountCode $discount): RedirectResponse
    {
        $discount->delete();

        return redirect()->route('tenant.admin.takeaway.discounts.index')->with('status', __('takeaway.code_deleted'));
    }

    /** Form tạo đơn hỏi để tính thử. Khi tạo đơn, PlaceOrder vẫn kiểm tra lại. */
    public function check(Request $request): JsonResponse
    {
        $code = CustomerOffer::usableCode($request->query('code')) ?? DiscountCode::usable($request->query('code'));

        return response()->json($code
            ? ['valid' => true, 'type' => $code->type->value, 'value' => $code->value, 'label' => $code->type->label($code->value)]
            : ['valid' => false, 'message' => __('takeaway.code_invalid')]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?DiscountCode $discount = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('discount_codes', 'code')->where('restaurant_id', tenant()->getKey())->ignore($discount),
            ],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => [
                'required', 'integer', 'min:1',
                'max:'.($request->input('type') === DiscountType::Percent->value ? 100 : 999999999),
            ],
        ]);

        return [...$data, 'is_active' => $request->boolean('is_active')];
    }
}
