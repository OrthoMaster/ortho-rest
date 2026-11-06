<?php

use App\Modules\Reservation\Http\AreaController;
use App\Modules\Reservation\Http\CustomerController;
use App\Modules\Reservation\Http\ReservationController;
use App\Modules\Reservation\Http\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('module:reservation')
    ->prefix('reservations')
    ->name('reservation.')
    ->group(function () {
        Route::get('/', [ReservationController::class, 'index'])->name('index');
        Route::get('create', [ReservationController::class, 'create'])->name('create');
        Route::post('/', [ReservationController::class, 'store'])->name('store');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers');

        Route::middleware('role:owner,manager')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'edit'])->name('edit');
            Route::put('booking', [SettingsController::class, 'updateBooking'])->name('booking');
        });

        Route::middleware('role:owner,manager')->prefix('areas')->name('areas.')->group(function () {
            Route::get('/', [AreaController::class, 'index'])->name('index');
            Route::post('/', [AreaController::class, 'store'])->name('store');
            Route::post('reorder', [AreaController::class, 'reorder'])->name('reorder');
            Route::put('{area}', [AreaController::class, 'update'])->whereNumber('area')->name('update');
            Route::delete('{area}', [AreaController::class, 'destroy'])->whereNumber('area')->name('destroy');
        });

        Route::get('{reservation}', [ReservationController::class, 'show'])->whereNumber('reservation')->name('show');
        Route::get('{reservation}/edit', [ReservationController::class, 'edit'])->whereNumber('reservation')->name('edit');
        Route::put('{reservation}', [ReservationController::class, 'update'])->whereNumber('reservation')->name('update');
        Route::post('{reservation}/status', [ReservationController::class, 'changeStatus'])->whereNumber('reservation')->name('status');
    });
