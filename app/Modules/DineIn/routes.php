<?php

use App\Modules\DineIn\Http\PollController;
use App\Modules\DineIn\Http\StationController;
use App\Modules\DineIn\Http\TableController;
use Illuminate\Support\Facades\Route;

Route::middleware('module:dinein')
    ->prefix('dinein')
    ->name('dinein.')
    ->group(function () {
        Route::get('/', [TableController::class, 'home'])->name('index');
        Route::get('poll', PollController::class)->middleware('can_any:dinein.order,kitchen.screen')->name('poll');

        // Bàn: số bàn là mã khu vực + số, ví dụ A12.
        Route::middleware('can:dinein.order')->group(function () {
            Route::get('tables', [TableController::class, 'index'])->name('tables.index');
            Route::post('tables/go', [TableController::class, 'go'])->name('tables.go');
            Route::get('tables/{label}', [TableController::class, 'show'])->where('label', '[A-Za-z]{1,2}[0-9]{1,3}')->name('tables.show');
            Route::get('tables/{label}/order', [TableController::class, 'create'])->where('label', '[A-Za-z]{1,2}[0-9]{1,3}')->name('tables.order');
            Route::post('tables/{label}/order', [TableController::class, 'store'])->where('label', '[A-Za-z]{1,2}[0-9]{1,3}')->name('tables.store');
            Route::post('lines/{line}/serve', [TableController::class, 'serve'])->whereNumber('line')->name('lines.serve');
            Route::post('lines/{line}/cancel', [TableController::class, 'cancel'])->whereNumber('line')->name('lines.cancel');
        });
        Route::post('tables/{table}/close', [TableController::class, 'close'])->whereNumber('table')->middleware('can:dinein.close')->name('tables.close');
    });

// Khu phục vụ (Bếp, Bar...) ở Cài đặt chung → Khu vực: dùng cho Lấy đơn tại bàn, Màn hình khu và Lịch làm việc.
Route::middleware(['module:dinein,kitchen', 'can_any:dinein.settings,settings.manage'])->prefix('dinein/stations')->name('dinein.stations.')->group(function () {
    Route::get('/', [StationController::class, 'index'])->name('index');
    Route::post('/', [StationController::class, 'store'])->name('store');
    Route::post('reorder', [StationController::class, 'reorder'])->name('reorder');
    Route::get('{station}/edit', [StationController::class, 'edit'])->whereNumber('station')->name('edit');
    Route::put('{station}', [StationController::class, 'update'])->whereNumber('station')->name('update');
    Route::delete('{station}', [StationController::class, 'destroy'])->whereNumber('station')->name('destroy');
});
