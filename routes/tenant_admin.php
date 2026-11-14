<?php

// Phần quản lý của một quán: domain-chinh/{slug}/admin/...

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\CapacityController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\MailController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RestaurantController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SpecialDayController;
use App\Http\Controllers\AppController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
Route::put('profile/devices/lock', [DeviceController::class, 'lock'])->name('profile.devices.lock');
Route::delete('profile/devices/{device}', [DeviceController::class, 'destroy'])->whereNumber('device')->name('profile.devices.destroy');

// Máy này bật thông báo đẩy.
Route::post('push', [AppController::class, 'subscribe'])->middleware('throttle:30,1')->name('push.subscribe');

// Khách hàng dùng chung cho đặt bàn và đặt mang đi.
Route::get('customers', [CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
Route::get('customers/export', [CustomerController::class, 'export'])->middleware('can:customers.export')->name('customers.export');
Route::post('customers/{customer}/anonymize', [CustomerController::class, 'anonymize'])->whereNumber('customer')->middleware('can:customers.anonymize')->name('customers.anonymize');
Route::get('activity', [ActivityController::class, 'index'])->middleware('can:activity.view')->name('activity');

// Thống kê cho chủ quán: những gì máy tính tiền không đo được (tốc độ ra món, sự cố, huỷ món…).
Route::middleware('can:reports.view')->prefix('reports')->name('reports')->group(function () {
    Route::get('{tab?}', [ReportController::class, 'show'])->where('tab', implode('|', ReportController::TABS))->name('');
    Route::get('{tab}/export', [ReportController::class, 'export'])->where('tab', implode('|', ReportController::TABS))->name('.export');
});

// Email của quán (gửi khách khi đặt bàn): máy chủ gửi, mẫu email.
Route::middleware(['can:settings.manage', 'module:reservation'])->prefix('email')->name('mail.')->group(function () {
    Route::get('/', [MailController::class, 'server'])->name('server');
    Route::put('/', [MailController::class, 'updateServer'])->name('server.update');
    Route::post('test', [MailController::class, 'test'])->middleware('throttle:10,1')->name('test');
    Route::get('mau', [MailController::class, 'templates'])->name('templates');
    Route::get('mau/{key}', [MailController::class, 'edit'])->where('key', '[a-z_]+')->name('edit');
    Route::put('mau/{key}', [MailController::class, 'update'])->where('key', '[a-z_]+')->name('update');
    Route::delete('mau/{key}', [MailController::class, 'reset'])->where('key', '[a-z_]+')->name('reset');
    Route::post('mau/{key}/test', [MailController::class, 'testTemplate'])->where('key', '[a-z_]+')->middleware('throttle:10,1')->name('template.test');
});

Route::middleware('can:settings.manage')->prefix('restaurant')->name('restaurant.')->group(function () {
    Route::get('/', [RestaurantController::class, 'edit'])->name('edit');
    Route::put('contact', [RestaurantController::class, 'updateContact'])->name('contact');
    Route::get('activity', fn () => redirect()->route('tenant.admin.activity', status: 301));

    Route::prefix('capacity')->name('capacity.')->group(function () {
        Route::get('/', [CapacityController::class, 'index'])->name('index');
        Route::post('/', [CapacityController::class, 'store'])->name('store');
        Route::post('reorder', [CapacityController::class, 'reorder'])->name('reorder');
        Route::put('{area}', [CapacityController::class, 'update'])->whereNumber('area')->name('update');
        Route::delete('{area}', [CapacityController::class, 'destroy'])->whereNumber('area')->name('destroy');
    });

    Route::get('special-days', [SpecialDayController::class, 'index'])->name('special-days.index');
    Route::get('special-days/create', [SpecialDayController::class, 'create'])->name('special-days.create');
    Route::post('special-days', [SpecialDayController::class, 'store'])->name('special-days.store');
    Route::get('special-days/{specialDay}/edit', [SpecialDayController::class, 'edit'])->whereNumber('specialDay')->name('special-days.edit');
    Route::put('special-days/{specialDay}', [SpecialDayController::class, 'update'])->whereNumber('specialDay')->name('special-days.update');
    Route::delete('special-days/{specialDay}', [SpecialDayController::class, 'destroy'])->whereNumber('specialDay')->name('special-days.destroy');
    Route::put('customer-data', [RestaurantController::class, 'updateCustomerData'])->name('customer-data');
    Route::put('sound', [RestaurantController::class, 'updateSound'])->middleware('module:kitchen')->name('sound');
    Route::put('order-window', [RestaurantController::class, 'updateOrderWindow'])->middleware(['module:dinein', 'module:schedule'])->name('order-window');
    Route::post('logo', [RestaurantController::class, 'updateLogo'])->name('logo.update');
    Route::delete('logo', [RestaurantController::class, 'destroyLogo'])->name('logo.destroy');
    Route::put('theme', [RestaurantController::class, 'updateTheme'])->name('theme.update');
    Route::delete('theme', [RestaurantController::class, 'destroyTheme'])->name('theme.destroy');

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
