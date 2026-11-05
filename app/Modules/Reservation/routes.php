<?php

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
            Route::post('closed-dates', [SettingsController::class, 'storeClosedDate'])->name('closed-dates.store');
            Route::delete('closed-dates/{date}', [SettingsController::class, 'destroyClosedDate'])->name('closed-dates.destroy');
            Route::post('areas', [SettingsController::class, 'storeArea'])->name('areas.store');
            Route::put('areas/{area}', [SettingsController::class, 'updateArea'])->name('areas.update');
            Route::delete('areas/{area}', [SettingsController::class, 'destroyArea'])->name('areas.destroy');
        });

        Route::get('{reservation}', [ReservationController::class, 'show'])->whereNumber('reservation')->name('show');
        Route::get('{reservation}/edit', [ReservationController::class, 'edit'])->whereNumber('reservation')->name('edit');
        Route::put('{reservation}', [ReservationController::class, 'update'])->whereNumber('reservation')->name('update');
        Route::post('{reservation}/status', [ReservationController::class, 'changeStatus'])->whereNumber('reservation')->name('status');
    });
