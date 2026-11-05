<?php

use App\Models\Restaurant;
use App\Tenancy\TenantContext;
use Carbon\CarbonInterface;
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

if (! function_exists('versioned_asset')) {
    /** Link file tĩnh kèm thời gian sửa, để trình duyệt tự lấy bản mới sau khi deploy. */
    function versioned_asset(string $path): string
    {
        return asset($path).'?v='.filemtime(public_path($path));
    }
}

if (! function_exists('back_url')) {
    /** Link về trang cha. Vừa đi từ trang cha thì giữ cả bộ lọc đang chọn trên URL. */
    function back_url(string $parent): string
    {
        $previous = url()->previous();

        return strtok($previous, '?') === strtok($parent, '?') ? $previous : $parent;
    }
}

if (! function_exists('vnd')) {
    function vnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' ₫';
    }
}

if (! function_exists('day_label')) {
    /** Hôm nay, Thứ năm 05/11. Khác năm hiện tại thì thêm năm để không nhầm. */
    function day_label(CarbonInterface $date): string
    {
        $label = __('ui.calendar.weekdays')[$date->dayOfWeekIso - 1].' '.$date->format($date->year === now()->year ? 'd/m' : 'd/m/Y');

        return match (true) {
            $date->isToday() => __('ui.calendar.today').', '.$label,
            $date->isTomorrow() => __('ui.calendar.tomorrow').', '.$label,
            default => $label,
        };
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
