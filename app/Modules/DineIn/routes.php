<?php

use App\Modules\DineIn\Http\GuestController;
use App\Modules\DineIn\Http\IncidentController;
use App\Modules\DineIn\Http\OfferController;
use App\Modules\DineIn\Http\PollController;
use App\Modules\DineIn\Http\StationController;
use App\Modules\DineIn\Http\TableController;
use Illuminate\Support\Facades\Route;

Route::middleware('module:dinein')
    ->prefix('dinein')
    ->name('dinein.')
    ->group(function () {
        Route::get('/', [TableController::class, 'home'])->name('index');

        // Bàn: số bàn là mã khu vực + số, ví dụ A12.
        Route::middleware('can:dinein.order')->group(function () {
            Route::get('tables', [TableController::class, 'index'])->name('tables.index');
            Route::post('tables/go', [TableController::class, 'go'])->name('tables.go');
            Route::get('tables/{label}', [TableController::class, 'show'])->where('label', '[A-Za-z]{1,2}[0-9]{1,3}')->name('tables.show');
            Route::get('tables/{label}/order', [TableController::class, 'create'])->where('label', '[A-Za-z]{1,2}[0-9]{1,3}')->name('tables.order');
            Route::post('tables/{label}/order', [TableController::class, 'store'])->where('label', '[A-Za-z]{1,2}[0-9]{1,3}')->name('tables.store');
            Route::post('lines/{line}/serve', [TableController::class, 'serve'])->whereNumber('line')->name('lines.serve');
            Route::post('lines/{line}/cancel', [TableController::class, 'cancel'])->whereNumber('line')->name('lines.cancel');

            // Khách và sự cố của bàn đang có khách.
            Route::post('tables/{table}/guest', [GuestController::class, 'attach'])->whereNumber('table')->name('tables.guest');
            Route::delete('tables/{table}/guest', [GuestController::class, 'detach'])->whereNumber('table')->name('tables.guest.remove');
            Route::post('tables/{table}/incidents', [GuestController::class, 'incident'])->whereNumber('table')->name('tables.incidents');
            Route::post('tables/{table}/gift', [GuestController::class, 'gift'])->whereNumber('table')->middleware('can:dinein.offers')->name('tables.gift');
        });
        Route::middleware('can:dinein.close')->group(function () {
            Route::post('tables/{table}/offers/{offer}', [GuestController::class, 'apply'])->whereNumber(['table', 'offer'])->name('tables.offer');
            Route::post('tables/{table}/voucher', [GuestController::class, 'applyCode'])->whereNumber('table')->name('tables.voucher');
            Route::delete('tables/{table}/discount', [GuestController::class, 'removeDiscount'])->whereNumber('table')->name('tables.discount.remove');
        });
        // Ưu đãi lần sau đã tặng (có tên và không tên).
        Route::get('offers', [OfferController::class, 'index'])->middleware('can:dinein.vouchers')->name('offers');
        Route::delete('offers/{offer}', [OfferController::class, 'destroy'])->middleware('can:dinein.offers')->whereNumber('offer')->name('offers.destroy');
        Route::middleware('can:dinein.incidents')->group(function () {
            Route::get('incidents', [IncidentController::class, 'index'])->name('incidents');
            Route::get('incidents/export', [IncidentController::class, 'export'])->name('incidents.export');
        });
        Route::post('tables/{table}/close', [TableController::class, 'close'])->whereNumber('table')->middleware('can:dinein.close')->name('tables.close');
    });

// Báo món trên mọi trang: chỉ có khi bật Màn hình khu vực (không bật thì món ra luôn, không có bước làm xong).
// Bật được cả khi chỉ có Đặt mang đi (báo món mới cho người ở khu).
Route::get('dinein/poll', PollController::class)->middleware(['module:kitchen', 'can_any:dinein.order,kitchen.screen'])->name('dinein.poll');

// Khu phục vụ (Bếp, Bar...) ở Cài đặt chung → Khu vực: dùng cho Lấy đơn tại bàn, Màn hình khu và Lịch làm việc.
Route::middleware(['module:dinein,kitchen', 'can_any:dinein.settings,settings.manage'])->prefix('dinein/stations')->name('dinein.stations.')->group(function () {
    Route::get('/', [StationController::class, 'index'])->name('index');
    Route::post('/', [StationController::class, 'store'])->name('store');
    Route::post('reorder', [StationController::class, 'reorder'])->name('reorder');
    Route::get('{station}/edit', [StationController::class, 'edit'])->whereNumber('station')->name('edit');
    Route::put('{station}', [StationController::class, 'update'])->whereNumber('station')->name('update');
    Route::delete('{station}', [StationController::class, 'destroy'])->whereNumber('station')->name('destroy');
});
