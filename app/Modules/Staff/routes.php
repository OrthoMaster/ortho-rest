<?php

use App\Modules\Staff\Http\AccountController;
use App\Modules\Staff\Http\PositionController;
use Illuminate\Support\Facades\Route;

Route::middleware('module:staff')
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route(auth()->user()->can('staff.manage') ? 'tenant.admin.staff.accounts.index' : 'tenant.admin.staff.positions.index'))
            ->middleware('can_any:staff.manage,positions.manage')
            ->name('index');

        Route::middleware('can:staff.manage')->prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::get('create', [AccountController::class, 'create'])->name('create');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('{account}/edit', [AccountController::class, 'edit'])->whereNumber('account')->name('edit');
            Route::put('{account}', [AccountController::class, 'update'])->whereNumber('account')->name('update');
            Route::post('{account}/active', [AccountController::class, 'toggleActive'])->whereNumber('account')->name('active');
        });

        Route::middleware('can:positions.manage')->prefix('positions')->name('positions.')->group(function () {
            Route::get('/', [PositionController::class, 'index'])->name('index');
            Route::get('create', [PositionController::class, 'create'])->name('create');
            Route::post('/', [PositionController::class, 'store'])->name('store');
            Route::post('reorder', [PositionController::class, 'reorder'])->name('reorder');
            Route::get('{position}/edit', [PositionController::class, 'edit'])->whereNumber('position')->name('edit');
            Route::put('{position}', [PositionController::class, 'update'])->whereNumber('position')->name('update');
            Route::delete('{position}', [PositionController::class, 'destroy'])->whereNumber('position')->name('destroy');
        });
    });
