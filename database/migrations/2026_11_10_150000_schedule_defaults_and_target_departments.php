<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Một khu có thể nhận nhiều bộ phận; lịch mặc định theo thứ × ca × hàng (số người cần
 * và người mặc định) để áp vào tuần và nhắc ô thiếu người.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['dinein_stations', 'areas'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('department_ids')->nullable();
            });
            DB::table($name)->whereNotNull('department_id')->orderBy('id')->each(function ($row) use ($name) {
                DB::table($name)->where('id', $row->id)->update(['department_ids' => json_encode([(int) $row->department_id])]);
            });
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        Schema::create('schedule_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->foreignId('shift_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('dinein_station_id')->nullable()->constrained('dinein_stations')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('needed')->nullable();
            $table->json('user_ids')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_defaults');
        foreach (['dinein_stations', 'areas'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->dropColumn('department_ids');
            });
        }
    }
};
