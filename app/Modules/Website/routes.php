<?php

use App\Modules\Website\Http\BookingPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:website', 'can:website.manage'])
    ->prefix('website')
    ->name('website.')
    ->group(function () {
        Route::get('/', [BookingPageController::class, 'index'])->name('index');

        // Web đặt trước: chỉ có khi bật cả Đặt bàn trước.
        Route::middleware('module:reservation')->prefix('dat-ban')->group(function () {
            Route::get('/', [BookingPageController::class, 'show'])->name('booking');
            Route::put('mode', [BookingPageController::class, 'mode'])->name('booking.mode');
            Route::post('upload', [BookingPageController::class, 'upload'])->name('booking.upload');
            Route::get('xem-thu', [BookingPageController::class, 'preview'])->name('booking.preview');
            Route::post('publish', [BookingPageController::class, 'publish'])->name('booking.publish');
            Route::delete('draft', [BookingPageController::class, 'discard'])->name('booking.discard');
            Route::post('versions/{page}/restore', [BookingPageController::class, 'restore'])->whereNumber('page')->name('booking.restore');
        });
    });
