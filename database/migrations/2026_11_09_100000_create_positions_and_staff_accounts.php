<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nội bộ: chức vụ do quán tự tạo, mỗi chức vụ một bộ quyền; một người có thể
 * giữ nhiều chức vụ. Nhân viên đăng nhập bằng số điện thoại hoặc email.
 *
 * Vai trò cố định Quản lý thành chức vụ "Quản lý", Nhân viên thành chức vụ
 * "Nhân viên". Quán đang có nhân viên được bật sẵn module Nội bộ để không ai
 * mất quyền.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('preset', 20)->nullable();
            $table->json('permissions');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['restaurant_id', 'sort_order']);
        });

        Schema::create('position_user', function (Blueprint $table) {
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['position_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->unique(['restaurant_id', 'phone']);
        });

        $catalog = require config_path('permissions.php');

        foreach (DB::table('users')->whereIn('role', ['manager', 'staff'])->distinct()->pluck('restaurant_id') as $restaurantId) {
            $enabled = ['core', 'staff', ...DB::table('restaurant_modules')->where('restaurant_id', $restaurantId)->where('enabled', true)->pluck('module')->all()];
            $ids = [];

            foreach (['manager' => 'Quản lý', 'staff' => 'Nhân viên'] as $preset => $name) {
                $permissions = [];

                foreach ($catalog as $module => $keys) {
                    if (in_array($module, $enabled, true)) {
                        $permissions = [...$permissions, ...array_keys(array_filter($keys, fn ($presets) => in_array($preset, $presets, true)))];
                    }
                }

                $ids[$preset] = DB::table('positions')->insertGetId([
                    'restaurant_id' => $restaurantId, 'name' => $name, 'preset' => $preset,
                    'permissions' => json_encode($permissions), 'sort_order' => $preset === 'manager' ? 1 : 2,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            foreach (DB::table('users')->where('restaurant_id', $restaurantId)->whereIn('role', ['manager', 'staff'])->get(['id', 'role']) as $user) {
                DB::table('position_user')->insert(['position_id' => $ids[$user->role], 'user_id' => $user->id]);
            }

            DB::table('restaurant_modules')->updateOrInsert(
                ['restaurant_id' => $restaurantId, 'module' => 'staff'],
                ['enabled' => true, 'enabled_at' => now(), 'settings' => json_encode([]), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        DB::table('users')->where('role', 'manager')->update(['role' => 'staff']);
    }

    public function down(): void
    {
        $managers = DB::table('position_user')->join('positions', 'positions.id', '=', 'position_user.position_id')
            ->where('positions.preset', 'manager')->pluck('position_user.user_id');
        DB::table('users')->whereIn('id', $managers)->update(['role' => 'manager']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'phone']);
            $table->dropColumn(['phone', 'is_active']);
        });

        Schema::dropIfExists('position_user');
        Schema::dropIfExists('positions');
    }
};
