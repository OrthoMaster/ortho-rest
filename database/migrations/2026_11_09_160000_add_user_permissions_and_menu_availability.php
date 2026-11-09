<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quyền riêng giao thẳng cho từng người (ngoại lệ ngoài chức vụ), và quyền mới
 * "Báo hết món / còn món" tách khỏi quyền sửa menu: chức vụ đang sửa được menu
 * giữ luôn quyền báo hết món như trước.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('is_active');
        });

        foreach (DB::table('positions')->get(['id', 'permissions']) as $position) {
            $keys = json_decode($position->permissions, true) ?? [];

            if (in_array('menu.manage', $keys, true) && ! in_array('menu.availability', $keys, true)) {
                DB::table('positions')->where('id', $position->id)->update(['permissions' => json_encode([...$keys, 'menu.availability'])]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
