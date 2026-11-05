<?php

use App\Modules\Takeaway\Http\OrderController;
use App\Modules\Takeaway\Http\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('module:takeaway')
    ->prefix('takeaway')
    ->name('takeaway.')
    ->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('create', [OrderController::class, 'create'])->name('create');
        Route::post('/', [OrderController::class, 'store'])->name('store');

        Route::middleware('role:owner,manager')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });

        Route::get('{order}', [OrderController::class, 'show'])->whereNumber('order')->name('show');
        Route::post('{order}/status', [OrderController::class, 'changeStatus'])->whereNumber('order')->name('status');
    });
