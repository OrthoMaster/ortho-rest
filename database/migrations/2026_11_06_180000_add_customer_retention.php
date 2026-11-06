<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thời hạn giữ thông tin khách của từng quán, và dấu đã ẩn danh của khách. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->unsignedSmallInteger('customer_retention_days')->default(60);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('customer_retention_days');
        });
    }
};
