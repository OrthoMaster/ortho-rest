<?php

use App\Http\Middleware\EnsureMainDomain;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureRestaurantActive;
use App\Http\Middleware\EnsureRestaurantMember;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\IdentifyTenantByHost;
use App\Http\Middleware\IdentifyTenantBySlug;
use App\Http\Middleware\RedirectToCustomDomain;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Không qua nhóm web: Caddy gọi endpoint này, không cần session hay cookie.
        then: fn () => Route::group([], base_path('routes/internal.php')),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'main.domain' => EnsureMainDomain::class,
            'module' => EnsureModuleEnabled::class,
            'role' => EnsureRole::class,
            'super' => EnsureSuperAdmin::class,
            'tenant.active' => EnsureRestaurantActive::class,
            'tenant.host' => IdentifyTenantByHost::class,
            'tenant.member' => EnsureRestaurantMember::class,
            'tenant.redirect' => RedirectToCustomDomain::class,
            'tenant.slug' => IdentifyTenantBySlug::class,
        ]);

        $middleware->web(append: [SetLocale::class]);

        // Phải biết nhà hàng trước khi nạp model từ URL, để Global Scope lọc đúng quán.
        $middleware->prependToPriorityList(SubstituteBindings::class, IdentifyTenantBySlug::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeUrl());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
