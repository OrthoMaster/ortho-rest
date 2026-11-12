<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Công bố lịch theo tuần: tuần chưa công bố là bản nháp, chỉ người phân công thấy.
 * Các tuần đã có phân công từ trước được coi là đã công bố, để nhân viên vẫn thấy như cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['restaurant_id', 'week_start']);
        });

        $now = now();
        $weeks = DB::table('schedule_assignments')
            ->selectRaw('restaurant_id, DATE_SUB(work_date, INTERVAL WEEKDAY(work_date) DAY) as week_start')
            ->distinct()
            ->get()
            ->map(fn ($row) => ['restaurant_id' => $row->restaurant_id, 'week_start' => $row->week_start, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now])
            ->all();

        foreach (array_chunk($weeks, 500) as $chunk) {
            DB::table('schedule_weeks')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_weeks');
    }
};
