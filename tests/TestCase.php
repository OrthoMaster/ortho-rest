<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Position;
use App\Models\PushSubscription;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Push\WebPush;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ghi lại thông báo đẩy thay vì gửi thật (có khoá VAPID giả). Mỗi phần tử: users (id người nhận) + nội dung.
     * Mỗi người nhận cần một PushSubscription (xem pushDevice()).
     */
    protected function recordPushes(): \ArrayObject
    {
        config(['liresto.push.public_key' => 'public', 'liresto.push.private_key' => 'private']);
        $sent = new \ArrayObject;
        $this->app->instance(WebPush::class, new class($sent) extends WebPush
        {
            public function __construct(private \ArrayObject $log) {}

            public function send(Collection $subscriptions, array $message): void
            {
                $this->log[] = ['users' => $subscriptions->pluck('user_id')->all(), ...$message];
            }
        });

        return $sent;
    }

    protected function pushDevice(User $user): PushSubscription
    {
        return PushSubscription::create(['user_id' => $user->id, 'endpoint' => 'https://push.test/'.$user->id, 'endpoint_hash' => hash('sha256', (string) $user->id), 'public_key' => 'k', 'auth_token' => 'a']);
    }

    protected function actingInTenant(Restaurant $restaurant): void
    {
        app(TenantContext::class)->set($restaurant);
    }

    protected function enableModules(Restaurant $restaurant, string ...$modules): void
    {
        // Bật module là thao tác của super admin, không nằm trong quán nào.
        app(TenantContext::class)->forget();

        foreach ($modules as $module) {
            app(ModuleManager::class)->enable($restaurant, $module);
        }
    }

    /**
     * Người của quán không phải chủ quán: bật Nội bộ nếu chưa bật, rồi giao chức
     * vụ mặc định "manager" (Quản lý) hoặc "staff" (Nhân viên).
     */
    protected function member(Restaurant $restaurant, string $preset = 'staff', array $attributes = []): User
    {
        $context = app(TenantContext::class);
        $current = $context->get();

        if (! app(ModuleManager::class)->isEnabled($restaurant, 'staff')) {
            $this->enableModules($restaurant, 'staff');
        }

        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'role' => UserRole::Staff, ...$attributes]);
        $user->positions()->attach(Position::forRestaurant($restaurant)->where('preset', $preset)->firstOrFail());

        if ($current) {
            $context->set($current);
        }

        return $user;
    }
}
