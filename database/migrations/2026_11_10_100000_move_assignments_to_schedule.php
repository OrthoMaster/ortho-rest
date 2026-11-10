<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phân công chuyển từ Lấy đơn tại bàn sang module Lịch làm việc: đổi tên bảng,
 * bỏ quyền dinein.assign (bật Lịch làm việc thì chức vụ nhận quyền schedule.*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('dinein_assignments', 'schedule_assignments');

        foreach (['positions', 'users'] as $table) {
            DB::table($table)->whereNotNull('permissions')->orderBy('id')->each(function ($row) use ($table) {
                $permissions = json_decode($row->permissions, true);

                if (is_array($permissions) && in_array('dinein.assign', $permissions, true)) {
                    DB::table($table)->where('id', $row->id)->update([
                        'permissions' => json_encode(array_values(array_diff($permissions, ['dinein.assign']))),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::rename('schedule_assignments', 'dinein_assignments');
    }
};
