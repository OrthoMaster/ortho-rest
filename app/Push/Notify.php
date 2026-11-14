<?php

namespace App\Push;

use App\Models\PushSubscription;
use App\Models\User;

use function Illuminate\Support\defer;

/**
 * Gửi thông báo đẩy cho người trong quán (gửi sau khi trả trang, không làm chậm thao tác).
 * Bỏ qua người đang thao tác (tự mình làm thì không cần báo), và bỏ qua khi chưa có khoá VAPID.
 */
class Notify
{
    /**
     * @param  iterable<User>  $users
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $message
     */
    public static function users(iterable $users, array $message): void
    {
        if (! WebPush::enabled() || tenant() === null) {
            return;
        }

        $ids = collect($users)->map(fn (User $user) => $user->getKey())->reject(fn ($id) => $id === auth()->id())->unique()->values()->all();
        $subscriptions = $ids === [] ? collect() : PushSubscription::whereIn('user_id', $ids)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $message['icon'] ??= route('tenant.app-icon', ['size' => 192]);

        defer(fn () => app(WebPush::class)->send($subscriptions, $message));
    }

    /** Người trong quán có quyền này (chủ quán luôn có). */
    public static function permitted(string $permission, array $message): void
    {
        self::users(tenant()->users()->get()->filter(fn (User $user) => $user->can($permission)), $message);
    }
}
