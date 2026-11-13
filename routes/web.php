<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Super\ImpersonationController;
use App\Http\Controllers\Super\RestaurantController;
use App\Http\Controllers\Super\RestaurantDomainController;
use App\Http\Controllers\Super\RestaurantLogoController;
use App\Http\Controllers\Super\RestaurantModuleController;
use App\Http\Controllers\Super\RestaurantThemeController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

/*
| Host khác domain chính: web công khai của quán, không có phần nào khác.
| Host lạ hoặc chưa xác minh trả 404 trong tenant.host.
*/
$mainDomains = implode('|', array_map(fn (string $host) => preg_quote($host, '#'), config('liresto.main_domains')));

Route::domain('{customDomain}')
    ->where(['customDomain' => "(?!(?:$mainDomains)$).+"])
    ->middleware(['tenant.host', 'tenant.active', 'module:website'])
    ->name('domain.')
    ->group(base_path('routes/tenant_public.php'));

/*
| Domain chính.
*/
Route::middleware('main.domain')->group(function () {
    Route::view('/', 'public.platform')->name('home');

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');

        Route::get('forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:5,1')
            ->name('password.update');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::post('impersonate/leave', [ImpersonationController::class, 'leave'])->name('impersonate.leave');
    });

    Route::middleware(['auth', 'super'])->prefix('super')->name('super.')->group(function () {
        Route::redirect('/', '/super/restaurants');

        Route::resource('restaurants', RestaurantController::class)->except(['destroy', 'edit']);
        Route::post('restaurants/{restaurant}/lock', [RestaurantController::class, 'lock'])->name('restaurants.lock');
        Route::post('restaurants/{restaurant}/unlock', [RestaurantController::class, 'unlock'])->name('restaurants.unlock');

        Route::put('restaurants/{restaurant}/theme', [RestaurantThemeController::class, 'update'])->name('theme.update');
        Route::delete('restaurants/{restaurant}/theme', [RestaurantThemeController::class, 'destroy'])->name('theme.destroy');

        Route::post('restaurants/{restaurant}/logo', [RestaurantLogoController::class, 'update'])->name('logo.update');
        Route::delete('restaurants/{restaurant}/logo', [RestaurantLogoController::class, 'destroy'])->name('logo.destroy');

        Route::post('restaurants/{restaurant}/domains', [RestaurantDomainController::class, 'store'])->name('domains.store');
        Route::post('restaurants/{restaurant}/domains/{domain}/verify', [RestaurantDomainController::class, 'verify'])
            ->scopeBindings()
            ->name('domains.verify');
        Route::delete('restaurants/{restaurant}/domains/{domain}', [RestaurantDomainController::class, 'destroy'])
            ->scopeBindings()
            ->name('domains.destroy');

        Route::put('restaurants/{restaurant}/modules', [RestaurantModuleController::class, 'sync'])->name('modules.sync');
        Route::put('restaurants/{restaurant}/modules/{module}', [RestaurantModuleController::class, 'update'])->name('modules.update');

        Route::post('restaurants/{restaurant}/impersonate', [ImpersonationController::class, 'start'])->name('impersonate.start');
        Route::post('restaurants/{restaurant}/devices/unlock', [RestaurantController::class, 'unlockDevices'])->name('restaurants.devices.unlock');
    });

    Route::prefix('{resto}')
        ->where(['resto' => config('liresto.slug_pattern')])
        ->middleware('tenant.slug')
        ->group(function () {
            // Trang đăng nhập riêng của quán, mang màu và logo của quán.
            Route::name('tenant.')->middleware('guest')->group(function () {
                Route::get('login', [LoginController::class, 'create'])->name('login');
                Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
                Route::get('forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
                Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])
                    ->middleware('throttle:5,1')
                    ->name('password.email');
                Route::get('reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
                Route::post('reset-password', [PasswordResetController::class, 'reset'])
                    ->middleware('throttle:5,1')
                    ->name('password.update');
            });

            // Trang ưu đãi khách mở bằng mã QR: không cần đăng nhập, không cần bật Website.
            Route::get('uu-dai/{token}', VoucherController::class)->where('token', '[A-Za-z0-9]{32}')
                ->middleware('tenant.active')->name('tenant.voucher');

            Route::prefix('admin')
                ->name('tenant.admin.')
                ->middleware(['auth', 'tenant.member', 'tenant.active', 'tenant.prune'])
                ->group(base_path('routes/tenant_admin.php'));

            Route::name('tenant.')
                ->middleware(['tenant.redirect', 'tenant.active', 'module:website'])
                ->group(base_path('routes/tenant_public.php'));
        });
});
