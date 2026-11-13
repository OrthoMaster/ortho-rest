<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Dữ liệu chạy thử: super admin và nhà hàng Sài Gòn (đã hoạt động 3 tháng).
 * Mật khẩu mọi tài khoản là SEED_PASSWORD trong .env (mặc định "password").
 * Production chỉ chạy được khi đã đặt SEED_PASSWORD.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && blank(config('liresto.seed_password'))) {
            throw new RuntimeException('Đặt SEED_PASSWORD trong .env trước khi seed trên production (hoặc dùng: php artisan liresto:super-admin).');
        }

        self::account('fx.tiennguyen92@gmail.com', 'Super admin', superAdmin: true);

        $this->call(SaigonSeeder::class);
    }

    public static function account(
        string $email,
        string $name,
        ?Restaurant $restaurant = null,
        ?UserRole $role = null,
        bool $superAdmin = false,
        ?string $phone = null,
    ): User {
        $user = User::where('email', $email)->firstOr(fn () => new User(['email' => $email]));
        $user->forceFill([
            'name' => $name,
            'password' => config('liresto.seed_password') ?: 'password',
            'email_verified_at' => now(),
            'restaurant_id' => $restaurant?->getKey(),
            'role' => $role,
            'is_super_admin' => $superAdmin,
            'phone' => $phone,
        ])->save();

        return $user;
    }
}
