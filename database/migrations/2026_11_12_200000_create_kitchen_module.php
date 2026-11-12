<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Màn hình khu tách thành module riêng (kitchen):
 * - quán đang bật Lấy đơn tại bàn được bật sẵn Màn hình khu như trước;
 * - quyền "mở màn hình mọi khu" đổi tên dinein.stations → kitchen.stations;
 * - món chưa chọn khu không còn tự vào khu đầu tiên: món đang chờ lúc chuyển đổi
 *   được gán hẳn cho khu đầu tiên để vẫn hiện ở màn hình như cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (DB::table('restaurant_modules')->where('module', 'dinein')->where('enabled', true)->pluck('restaurant_id') as $restaurantId) {
            if (! DB::table('restaurant_modules')->where('restaurant_id', $restaurantId)->where('module', 'kitchen')->exists()) {
                DB::table('restaurant_modules')->insert(['restaurant_id' => $restaurantId, 'module' => 'kitchen', 'enabled' => true, 'enabled_at' => $now, 'settings' => '[]', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach (['positions', 'users'] as $table) {
            foreach (DB::table($table)->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
                $keys = json_decode($row->permissions, true) ?? [];

                if (in_array('dinein.stations', $keys, true)) {
                    $keys = array_values(array_unique(array_map(fn ($key) => $key === 'dinein.stations' ? 'kitchen.stations' : $key, $keys)));
                    DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode($keys)]);
                }
            }
        }

        foreach (DB::table('dinein_stations')->orderBy('sort_order')->orderBy('id')->get()->unique('restaurant_id') as $first) {
            DB::table('dinein_lines')->where('restaurant_id', $first->restaurant_id)->whereNull('dinein_station_id')
                ->whereIn('status', ['new', 'done'])->update(['dinein_station_id' => $first->id]);

            DB::table('takeaway_order_items')->where('restaurant_id', $first->restaurant_id)->whereNull('dinein_station_id')->whereNull('done_at')
                ->whereIn('takeaway_order_id', DB::table('takeaway_orders')->whereIn('status', ['new', 'preparing'])->select('id'))
                ->update(['dinein_station_id' => $first->id]);
        }

        // Danh sách module đang bật được nhớ trong cache.
        Cache::flush();
    }

    public function down(): void
    {
        DB::table('restaurant_modules')->where('module', 'kitchen')->delete();

        foreach (['positions', 'users'] as $table) {
            foreach (DB::table($table)->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
                $keys = json_decode($row->permissions, true) ?? [];

                if (in_array('kitchen.stations', $keys, true)) {
                    DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode(array_values(array_map(fn ($key) => $key === 'kitchen.stations' ? 'dinein.stations' : $key, $keys)))]);
                }
            }
        }

        Cache::flush();
    }
};
