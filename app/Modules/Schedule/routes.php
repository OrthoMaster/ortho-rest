<?php

use App\Modules\Schedule\Http\LeaveController;
use App\Modules\Schedule\Http\ScheduleController;
use App\Modules\Schedule\Http\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:schedule', 'can:schedule.open'])
    ->prefix('schedule')
    ->name('schedule.')
    ->group(function () {
        Route::get('/', [ScheduleController::class, 'home'])->name('index');
        Route::get('person', [ScheduleController::class, 'person'])->name('person');
        Route::get('week', [ScheduleController::class, 'week'])->middleware('can:schedule.view')->name('week');

        // Xếp lịch bằng kéo thả: mỗi lần thả, kéo sang ô khác hay bỏ ra đều lưu ngay.
        Route::middleware('can:schedule.manage')->group(function () {
            Route::post('assignments', [ScheduleController::class, 'store'])->name('assignments.store');
            Route::patch('assignments/{assignment}', [ScheduleController::class, 'update'])->whereNumber('assignment')->name('assignments.update');
            Route::delete('assignments/{assignment}', [ScheduleController::class, 'destroy'])->whereNumber('assignment')->name('assignments.destroy');
            Route::post('week/copy', [ScheduleController::class, 'copyWeek'])->name('week.copy');

            Route::get('settings', [SettingsController::class, 'index'])->name('settings');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('tasks', [SettingsController::class, 'storeTask'])->name('tasks.store');
            Route::post('tasks/reorder', [SettingsController::class, 'reorderTasks'])->name('tasks.reorder');
            Route::put('tasks/{task}', [SettingsController::class, 'updateTask'])->whereNumber('task')->name('tasks.update');
            Route::delete('tasks/{task}', [SettingsController::class, 'destroyTask'])->whereNumber('task')->name('tasks.destroy');
        });

        // Đơn xin nghỉ: ai cũng gửi được cho mình; người có quyền duyệt thấy mọi đơn và nhập thay.
        Route::prefix('leave')->name('leave.')->group(function () {
            Route::get('/', [LeaveController::class, 'index'])->name('index');
            Route::get('create', [LeaveController::class, 'create'])->name('create');
            Route::post('/', [LeaveController::class, 'store'])->name('store');
            Route::post('{leave}/cancel', [LeaveController::class, 'cancel'])->whereNumber('leave')->name('cancel');
            Route::post('{leave}/decide', [LeaveController::class, 'decide'])->whereNumber('leave')->middleware('can:schedule.leave')->name('decide');
        });
    });
