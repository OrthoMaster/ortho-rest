<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module Thu ngân: phục vụ đóng bàn (bàn trống ngay), phiếu chuyển sang Chờ thu tiền ở quầy;
     * đơn mang đi cũng chờ thu ở đó. Thu ngân bấm Đã thu tiền, nhập số hoá đơn máy tính tiền (không bắt buộc).
     */
    public function up(): void
    {
        foreach (['dinein_tables', 'takeaway_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->index(['restaurant_id', 'paid_at']);
            });
        }

        // Phiếu cũ coi như đã thu, để hàng chờ không bị dồn.
        DB::table('dinein_tables')->whereNotNull('closed_at')->update(['paid_at' => DB::raw('closed_at'), 'paid_by' => DB::raw('closed_by')]);
        DB::table('takeaway_orders')->where('status', 'delivered')->update(['paid_at' => DB::raw('updated_at')]);

        $now = now();

        foreach (DB::table('restaurant_modules')->whereIn('module', ['dinein', 'takeaway'])->where('enabled', true)->pluck('restaurant_id')->unique() as $restaurantId) {
            if (! DB::table('restaurant_modules')->where('restaurant_id', $restaurantId)->where('module', 'cashier')->exists()) {
                DB::table('restaurant_modules')->insert(['restaurant_id' => $restaurantId, 'module' => 'cashier', 'enabled' => true, 'enabled_at' => $now, 'settings' => '[]', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Quyền Phiếu đã đóng (dinein.bills) chuyển thành Đối soát; Quản lý có thêm Thu tiền.
        foreach (['positions', 'users'] as $table) {
            foreach (DB::table($table)->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
                $keys = json_decode($row->permissions, true) ?? [];

                if (in_array('dinein.bills', $keys, true)) {
                    $keys = array_values(array_unique([...array_diff($keys, ['dinein.bills']), 'cashier.collect', 'cashier.reconcile']));
                    DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode($keys)]);
                }
            }
        }

        Cache::flush();
    }

    public function down(): void
    {
        foreach (['dinein_tables', 'takeaway_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['restaurant_id', 'paid_at']);
                $table->dropConstrainedForeignId('paid_by');
                $table->dropColumn('paid_at');
            });
        }

        DB::table('restaurant_modules')->where('module', 'cashier')->delete();
    }
};
