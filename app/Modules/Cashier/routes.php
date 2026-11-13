<?php

use App\Modules\Cashier\Http\CashierController;
use App\Modules\Cashier\Http\ReconcileController;
use Illuminate\Support\Facades\Route;

// Thu ngân: thu tiền ở quầy (bàn đã đóng, đơn mang đi) và đối soát với máy tính tiền.
Route::middleware(['module:cashier', 'module:dinein,takeaway'])
    ->prefix('cashier')
    ->name('cashier.')
    ->group(function () {
        Route::get('/', [CashierController::class, 'index'])->middleware('can_any:cashier.collect,cashier.reconcile')->name('index');
        Route::post('{bill}/paid', [CashierController::class, 'collect'])->where('bill', '[to][0-9]+')->middleware('can:cashier.collect')->name('paid');
        Route::put('{bill}/number', [CashierController::class, 'number'])->where('bill', '[to][0-9]+')->middleware('can_any:cashier.collect,cashier.reconcile')->name('number');
        Route::get('reconcile', [ReconcileController::class, 'index'])->middleware('can:cashier.reconcile')->name('reconcile');
        Route::get('reconcile/export', [ReconcileController::class, 'export'])->middleware('can:cashier.reconcile')->name('reconcile.export');
    });
