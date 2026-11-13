<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Số hoá đơn của máy tính tiền (không bắt buộc): nhập lúc đóng bàn hoặc giao đơn mang đi,
     * để đối soát doanh thu máy tính tiền với Liresto. Quyền mới xem phiếu đã đóng.
     */
    public function up(): void
    {
        foreach (['dinein_tables', 'takeaway_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('bill_number', 40)->nullable();
                $table->index(['restaurant_id', 'bill_number']);
            });
        }

        // Ai được đóng bàn mà là Quản lý (có quyền tặng ưu đãi) thì xem được phiếu đã đóng.
        foreach (['positions', 'users'] as $table) {
            foreach (DB::table($table)->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
                $keys = json_decode($row->permissions, true) ?? [];

                if (in_array('dinein.offers', $keys, true) && ! in_array('dinein.bills', $keys, true)) {
                    DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode([...$keys, 'dinein.bills'])]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['dinein_tables', 'takeaway_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['restaurant_id', 'bill_number']);
                $table->dropColumn('bill_number');
            });
        }
    }
};
