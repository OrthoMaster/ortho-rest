<?php

use Illuminate\Support\Facades\Route;

// Màn hình thật làm ở giai đoạn 1C.
Route::middleware(['module:takeaway'])
    ->prefix('takeaway')
    ->name('takeaway.')
    ->group(function () {
        Route::view('/', 'admin.module-placeholder', ['module' => 'takeaway'])->name('index');
    });
