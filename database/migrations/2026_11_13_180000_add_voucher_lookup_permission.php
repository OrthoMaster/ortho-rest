<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Quyền mới Tra cứu ưu đãi: ai đang có quyền tặng ưu đãi thì có luôn (tab Ưu đãi trước đây theo quyền đó). */
    public function up(): void
    {
        foreach (['positions', 'users'] as $table) {
            foreach (DB::table($table)->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
                $keys = json_decode($row->permissions, true) ?? [];

                if (in_array('dinein.offers', $keys, true) && ! in_array('dinein.vouchers', $keys, true)) {
                    DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode([...$keys, 'dinein.vouchers'])]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['positions', 'users'] as $table) {
            foreach (DB::table($table)->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
                $keys = json_decode($row->permissions, true) ?? [];
                DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode(array_values(array_diff($keys, ['dinein.vouchers'])))]);
            }
        }
    }
};
