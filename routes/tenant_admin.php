<?php

// Phần quản lý của một quán: domain-chinh/{slug}/admin/...

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RestaurantController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SpecialDayController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
Route::put('profile/devices/lock', [DeviceController::class, 'lock'])->name('profile.devices.lock');
Route::delete('profile/devices/{device}', [DeviceController::class, 'destroy'])->whereNumber('device')->name('profile.devices.destroy');

// Khách hàng dùng chung cho đặt bàn và đặt mang đi.
Route::get('customers', [CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
Route::get('customers/export', [CustomerController::class, 'export'])->middleware('can:customers.export')->name('customers.export');
Route::post('customers/{customer}/anonymize', [CustomerController::class, 'anonymize'])->whereNumber('customer')->middleware('can:customers.anonymize')->name('customers.anonymize');
Route::get('activity', [ActivityController::class, 'index'])->middleware('can:activity.view')->name('activity');

Route::middleware('can:settings.manage')->prefix('restaurant')->name('restaurant.')->group(function () {
    Route::get('/', [RestaurantController::class, 'edit'])->name('edit');
    Route::get('activity', fn () => redirect()->route('tenant.admin.activity', status: 301));

    Route::get('special-days', [SpecialDayController::class, 'index'])->name('special-days.index');
    Route::get('special-days/create', [SpecialDayController::class, 'create'])->name('special-days.create');
    Route::post('special-days', [SpecialDayController::class, 'store'])->name('special-days.store');
    Route::get('special-days/{specialDay}/edit', [SpecialDayController::class, 'edit'])->whereNumber('specialDay')->name('special-days.edit');
    Route::put('special-days/{specialDay}', [SpecialDayController::class, 'update'])->whereNumber('specialDay')->name('special-days.update');
    Route::delete('special-days/{specialDay}', [SpecialDayController::class, 'destroy'])->whereNumber('specialDay')->name('special-days.destroy');
    Route::put('customer-data', [RestaurantController::class, 'updateCustomerData'])->name('customer-data');
    Route::post('logo', [RestaurantController::class, 'updateLogo'])->name('logo.update');
    Route::delete('logo', [RestaurantController::class, 'destroyLogo'])->name('logo.destroy');

    Route::prefix('shifts')->name('shifts.')->group(function () {
        Route::get('{type}', [ShiftController::class, 'index'])->whereIn('type', ['opening', 'work'])->name('index');
        Route::get('{type}/create', [ShiftController::class, 'create'])->whereIn('type', ['opening', 'work'])->name('create');
        Route::post('{type}', [ShiftController::class, 'store'])->whereIn('type', ['opening', 'work'])->name('store');
        Route::get('{shift}/edit', [ShiftController::class, 'edit'])->whereNumber('shift')->name('edit');
        Route::put('{shift}', [ShiftController::class, 'update'])->whereNumber('shift')->name('update');
        Route::delete('{shift}', [ShiftController::class, 'destroy'])->whereNumber('shift')->name('destroy');
    });
});

foreach (config('modules') as $module) {
    if (isset($module['routes'])) {
        require app_path('Modules/'.$module['routes']);
    }
}
