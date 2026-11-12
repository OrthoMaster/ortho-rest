<?php

use App\Modules\Kitchen\Http\ScreenController;
use Illuminate\Support\Facades\Route;

// Màn hình khu: người có quyền mở mọi khu, người đang được phân công ở khu đó,
// và (khi bật Đặt mang đi) người đổi được trạng thái đơn mang đi.
Route::middleware(['module:kitchen', 'can:kitchen.screen'])
    ->prefix('kitchen')
    ->name('kitchen.')
    ->group(function () {
        Route::get('/', [ScreenController::class, 'index'])->name('index');
        Route::get('takeaway', [ScreenController::class, 'takeaway'])->name('takeaway');
        Route::post('takeaway/{order}', [ScreenController::class, 'takeawayStatus'])->whereNumber('order')->name('takeaway.status');
        Route::get('{station}', [ScreenController::class, 'show'])->whereNumber('station')->name('show');
        Route::post('{station}/done', [ScreenController::class, 'done'])->whereNumber('station')->name('done');
    });
