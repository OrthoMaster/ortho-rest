<?php

namespace App\Modules\Cashier\Http;

use App\Http\Controllers\Controller;
use App\Modules\Cashier\Cashier;
use App\Modules\DineIn\Tables;
use App\Support\BillNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Màn hình quầy: chờ thu tiền, bàn đang phục vụ, đã thu hôm nay. Tự tải lại như bảng bàn. */
class CashierController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // Chỉ có quyền đối soát thì vào thẳng Đối soát.
        if (! $request->user()->can('cashier.collect')) {
            return redirect()->route('tenant.admin.cashier.reconcile');
        }

        $data = [
            'pending' => Cashier::pending(),
            'open' => Cashier::openTables(),
            'paid' => Cashier::paidToday(),
        ];

        return view($request->hasHeader('X-Board') ? 'modules.cashier.board' : 'modules.cashier.index', $data);
    }

    public function collect(Request $request, string $bill): RedirectResponse
    {
        $found = Cashier::find($bill);
        $number = $request->validateWithBag('bill-'.$bill, ['bill_number' => BillNumber::RULES])['bill_number'] ?? null;

        // Bàn đang phục vụ: Thanh toán và đóng bàn luôn (khách đã về, phục vụ chưa kịp đóng).
        $close = $request->boolean('close') && $found->isTable() && $found->model->isOpen();

        if ($close && $found->model->hasPendingLines()) {
            return back()->with('error', __('cashier.close_pending', ['table' => $found->model->label]));
        }

        try {
            Cashier::collect($found, $number, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($close) {
            Tables::close($found->model, $request->user());

            return back()->with('status', __('cashier.paid_closed', ['bill' => $found->title(), 'total' => vnd($found->total())]));
        }

        return back()->with('status', __('cashier.paid', ['bill' => $found->title(), 'total' => vnd($found->total())]));
    }

    public function number(Request $request, string $bill): RedirectResponse
    {
        $found = Cashier::find($bill);
        Cashier::updateNumber($found, $request->validateWithBag('number-'.$bill, ['bill_number' => BillNumber::RULES])['bill_number'] ?? null);

        return back()->with('status', __('cashier.number_saved', ['bill' => $found->title()]));
    }
}
