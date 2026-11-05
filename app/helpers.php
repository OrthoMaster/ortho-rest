<?php

use App\Models\Restaurant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

if (! function_exists('tenant')) {
    function tenant(): ?Restaurant
    {
        return app(TenantContext::class)->get();
    }
}

if (! function_exists('has_module')) {
    function has_module(string $module): bool
    {
        return tenant()?->hasModule($module) ?? false;
    }
}

if (! function_exists('vnd')) {
    function vnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' ₫';
    }
}

if (! function_exists('tenant_route')) {
    /**
     * Sinh link của nhà hàng, đúng kiểu URL: domain riêng nếu quán có domain đã
     * xác minh và route đó chạy trên domain riêng, còn lại dùng domain chính/{slug}.
     *
     * $name bỏ tiền tố, ví dụ 'public.home' hoặc 'admin.dashboard'.
     */
    function tenant_route(string $name, array $parameters = [], ?Restaurant $restaurant = null): string
    {
        $restaurant ??= tenant() ?? throw new LogicException('tenant_route() cần một nhà hàng.');

        if (Route::has("domain.$name") && ($domain = $restaurant->primaryDomain())) {
            return route("domain.$name", ['customDomain' => $domain] + $parameters);
        }

        return route("tenant.$name", ['resto' => $restaurant->slug] + $parameters);
    }
}
