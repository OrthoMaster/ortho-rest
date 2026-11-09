<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sức chứa: mỗi khu vực có mã (1–2 chữ in hoa, không trùng trong quán) và số
 * bàn tối đa, để gọi món tại bàn theo dạng A12. Khu vực đang có được đặt mã
 * A, B, C... theo thứ tự, số bàn tạm tính 4 chỗ một bàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->string('code', 2)->nullable()->after('name');
            $table->unsignedSmallInteger('table_count')->default(1)->after('capacity');
            $table->unique(['restaurant_id', 'code']);
        });

        foreach (DB::table('areas')->distinct()->pluck('restaurant_id') as $restaurantId) {
            $letter = 0;

            foreach (DB::table('areas')->where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $area) {
                DB::table('areas')->where('id', $area->id)->update([
                    'code' => $this->code($letter++),
                    'table_count' => max(1, (int) ceil($area->capacity / 4)),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'code']);
            $table->dropColumn(['code', 'table_count']);
        });
    }

    /** 0 → A, 25 → Z, 26 → AA... */
    private function code(int $index): string
    {
        return $index < 26 ? chr(65 + $index) : chr(64 + intdiv($index, 26)).chr(65 + $index % 26);
    }
};
