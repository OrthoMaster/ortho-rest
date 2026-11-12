<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Món mang đi hiện ở màn hình khu (bếp, bar) như món gọi tại bàn: khu làm món
 * (chưa chọn khu thì vào khu đầu tiên), lúc bấm Xong và ai bấm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('takeaway_order_items', function (Blueprint $table) {
            $table->foreignId('dinein_station_id')->nullable()->after('menu_item_id')->constrained('dinein_stations')->nullOnDelete();
            $table->timestamp('done_at')->nullable()->after('note');
            $table->foreignId('done_by')->nullable()->after('done_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('takeaway_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('done_by');
            $table->dropConstrainedForeignId('dinein_station_id');
            $table->dropColumn('done_at');
        });
    }
};
