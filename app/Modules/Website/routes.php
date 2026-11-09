<?php

use Illuminate\Support\Facades\Route;

// Màn hình thật làm ở giai đoạn 1C.
Route::middleware(['module:website', 'can:website.manage'])
    ->prefix('website')
    ->name('website.')
    ->group(function () {
        Route::view('/', 'admin.module-placeholder', ['module' => 'website'])->name('index');
    });
