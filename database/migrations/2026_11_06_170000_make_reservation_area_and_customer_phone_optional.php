<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Khu vực khi đặt bàn và số điện thoại của khách không còn bắt buộc. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Không trả lại NOT NULL được khi đã có dữ liệu trống: chỉ đổi khi không còn dòng nào trống.
        if (! DB::table('reservations')->whereNull('area_id')->exists()) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->foreignId('area_id')->nullable(false)->change();
            });
        }

        if (! DB::table('customers')->whereNull('phone')->exists()) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('phone', 20)->nullable(false)->change();
            });
        }
    }
};
