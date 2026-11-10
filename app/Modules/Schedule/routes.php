<?php

use App\Modules\Schedule\Http\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:schedule', 'can:schedule.open'])
    ->prefix('schedule')
    ->name('schedule.')
    ->group(function () {
        Route::get('/', [ScheduleController::class, 'home'])->name('index');
        Route::get('person', [ScheduleController::class, 'person'])->name('person');

        Route::middleware('can:schedule.view')->group(function () {
            Route::get('week', [ScheduleController::class, 'week'])->name('week');
            Route::get('day', [ScheduleController::class, 'day'])->name('day');
        });

        Route::middleware('can:schedule.manage')->group(function () {
            Route::put('day', [ScheduleController::class, 'update'])->name('day.update');
            Route::post('week/copy', [ScheduleController::class, 'copyWeek'])->name('week.copy');
        });
    });
