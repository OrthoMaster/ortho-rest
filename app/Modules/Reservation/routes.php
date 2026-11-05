<?php

use Illuminate\Support\Facades\Route;

// Màn hình thật làm ở giai đoạn 1C.
Route::middleware(['module:reservation'])
    ->prefix('reservations')
    ->name('reservation.')
    ->group(function () {
        Route::view('/', 'admin.module-placeholder', ['module' => 'reservation'])->name('index');
    });
