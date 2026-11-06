<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Khu vực: thêm thứ tự kéo thả. Cho đặt online không còn theo khu vực mà là số chỗ chung.
        Schema::table('areas', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('capacity');
        });

        foreach (DB::table('areas')->orderBy('id')->get()->groupBy('restaurant_id') as $areas) {
            foreach ($areas->values() as $position => $area) {
                DB::table('areas')->where('id', $area->id)->update(['sort_order' => $position + 1]);
            }
        }

        foreach (DB::table('restaurant_modules')->where('module', 'reservation')->get() as $state) {
            $old = json_decode($state->settings ?? '[]', true) ?: [];
            $areas = DB::table('areas')->where('restaurant_id', $state->restaurant_id)->where('is_active', true);
            $total = (int) (clone $areas)->sum('capacity');
            $online = (int) (clone $areas)->where('online_bookable', true)->sum('capacity');

            DB::table('restaurant_modules')->where('id', $state->id)->update(['settings' => json_encode([
                'turn_minutes' => (int) ($old['turn_minutes'] ?? 90),
                'online_enabled' => $online > 0,
                'min_notice_days' => intdiv((int) ($old['min_notice_minutes'] ?? 0), 1440),
                'max_advance_days' => (int) ($old['max_advance_days'] ?? 30),
                'auto_confirm' => false,
                'online_seats' => $online > 0 ? $online : $total,
                'online_seats_total' => $total,
            ])]);
        }

        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('online_bookable');
        });

        // Ngày đặc biệt: đóng cửa (cả ngày hoặc theo ca) và mở cửa thêm, có lý do.
        Schema::create('special_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10);
            $table->json('periods');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'date']);
        });

        foreach (DB::table('restaurants')->whereNotNull('closed_dates')->get() as $restaurant) {
            foreach (json_decode($restaurant->closed_dates, true) ?: [] as $closed) {
                DB::table('special_days')->insertOrIgnore([
                    'restaurant_id' => $restaurant->id,
                    'date' => $closed['date'],
                    'type' => 'closed',
                    'periods' => '[]',
                    'reason' => ($closed['note'] ?? '') !== '' ? $closed['note'] : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('closed_dates');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('closed_dates')->nullable();
        });

        foreach (DB::table('special_days')->where('type', 'closed')->whereJsonLength('periods', 0)->get()->groupBy('restaurant_id') as $restaurantId => $days) {
            DB::table('restaurants')->where('id', $restaurantId)->update(['closed_dates' => json_encode($days->map(fn ($day) => [
                'date' => $day->date,
                'note' => $day->reason,
            ])->values()->all())]);
        }

        Schema::dropIfExists('special_days');

        Schema::table('areas', function (Blueprint $table) {
            $table->boolean('online_bookable')->default(true)->after('capacity');
        });

        foreach (DB::table('restaurant_modules')->where('module', 'reservation')->get() as $state) {
            $new = json_decode($state->settings ?? '[]', true) ?: [];

            DB::table('areas')->where('restaurant_id', $state->restaurant_id)->update(['online_bookable' => (bool) ($new['online_enabled'] ?? true)]);
            DB::table('restaurant_modules')->where('id', $state->id)->update(['settings' => json_encode([
                'min_notice_minutes' => (int) ($new['min_notice_days'] ?? 0) * 1440,
                'max_advance_days' => (int) ($new['max_advance_days'] ?? 30),
                'turn_minutes' => (int) ($new['turn_minutes'] ?? 90),
                'slot_interval_minutes' => 30,
            ])]);
        }

        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
