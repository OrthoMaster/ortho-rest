<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Dữ liệu chạy thử ở local, chạy lại được. Mật khẩu yếu nên không chạy trên production. */
class DatabaseSeeder extends Seeder
{
    public function run(ModuleManager $modules): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Seed chạy thử không dùng cho production. Dùng: php artisan liresto:super-admin');
        }

        $this->user('super@liresto.test', 'Super admin', superAdmin: true);

        $demo = Restaurant::updateOrCreate(['slug' => 'quan-demo'], ['name' => 'Quán Demo']);
        $this->user('owner@quan-demo.test', 'Chủ Quán Demo', $demo, UserRole::Owner);
        $this->user('manager@quan-demo.test', 'Quản lý Demo', $demo, UserRole::Manager);
        $this->user('staff@quan-demo.test', 'Nhân viên Demo', $demo, UserRole::Staff);

        foreach (['website', 'menu', 'reservation', 'takeaway'] as $module) {
            $modules->enable($demo, $module);
        }

        // *.localhost tự trỏ về máy mình trên Chrome, Edge, Firefox: khỏi sửa file hosts.
        $custom = Restaurant::updateOrCreate(['slug' => 'bun-cha-ha-noi'], ['name' => 'Bún Chả Hà Nội']);
        $this->user('owner@bun-cha.test', 'Chủ Bún Chả', $custom, UserRole::Owner);
        $modules->enable($custom, 'website');

        $custom->domains()->updateOrCreate(['domain' => 'bun-cha.localhost'], ['verified_at' => now()]);
    }

    private function user(
        string $email,
        string $name,
        ?Restaurant $restaurant = null,
        ?UserRole $role = null,
        bool $superAdmin = false,
    ): void {
        User::where('email', $email)->firstOr(fn () => new User(['email' => $email]))
            ->forceFill([
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'restaurant_id' => $restaurant?->getKey(),
                'role' => $role,
                'is_super_admin' => $superAdmin,
            ])
            ->save();
    }
}
