<?php

// Phần quản lý của một quán: domain-chinh/{slug}/admin/...

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

foreach (config('modules') as $module) {
    if (isset($module['routes'])) {
        require app_path('Modules/'.$module['routes']);
    }
}
