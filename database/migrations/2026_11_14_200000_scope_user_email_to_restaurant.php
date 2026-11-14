<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email duy nhất trong từng quán (giống số điện thoại), không còn duy nhất toàn hệ thống:
 * một người dùng cùng email làm chủ hoặc làm việc ở nhiều quán, mỗi quán một tài khoản riêng.
 * Super admin (không thuộc quán) vẫn kiểm tra trùng email trong code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['restaurant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'email']);
            $table->unique(['email']);
        });
    }
};
