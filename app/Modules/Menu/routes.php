<?php

use Illuminate\Support\Facades\Route;

// Màn hình thật làm ở giai đoạn 1C.
Route::middleware(['module:menu', 'role:owner,manager'])
    ->prefix('menu')
    ->name('menu.')
    ->group(function () {
        Route::view('/', 'admin.module-placeholder', ['module' => 'menu'])->name('index');
    });
