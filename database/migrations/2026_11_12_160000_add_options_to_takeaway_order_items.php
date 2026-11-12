<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Món mang đi có tuỳ chọn (chép tên, giá lúc gọi) và ghi chú, giống gọi món tại bàn. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('takeaway_order_items', function (Blueprint $table) {
            $table->json('options')->nullable()->after('quantity');
            $table->string('note', 200)->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('takeaway_order_items', function (Blueprint $table) {
            $table->dropColumn(['options', 'note']);
        });
    }
};
