<?php

// Web công khai. File này được nạp hai lần: domain-chinh/{slug}/... và domain riêng.

use App\Http\Controllers\PublicSite\BookingController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Middleware\AllowAnyOrigin;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('public.home');

// Đặt bàn online: trang (mẫu hoặc quán tự tạo), các địa chỉ JSON cho script, link riêng của khách.
Route::middleware('module:reservation')->prefix('dat-ban')->name('public.booking')->group(function () {
    Route::get('/', [BookingController::class, 'page'])->name('');
    Route::get('mac-dinh', [BookingController::class, 'defaultPage'])->name('.default');
    Route::get('xem-thu/{page}', [BookingController::class, 'preview'])->whereNumber('page')->middleware('signed')->name('.preview');

    Route::middleware(AllowAnyOrigin::class)->group(function () {
        Route::get('thong-tin', [BookingController::class, 'info'])->name('.info');
        Route::get('gio-trong', [BookingController::class, 'slots'])->middleware('throttle:120,1')->name('.slots');
        Route::post('gui', [BookingController::class, 'submit'])->middleware('throttle:15,1')->name('.submit');
    });

    Route::get('{token}', [BookingController::class, 'status'])->where('token', '[A-Za-z0-9]{24}')->name('.status');
    Route::post('{token}/huy', [BookingController::class, 'cancel'])->where('token', '[A-Za-z0-9]{24}')->middleware('throttle:10,1')->name('.cancel');
});
