<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedTinyInteger('weekday');
            $table->string('name', 50);
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->timestamps();

            $table->index(['restaurant_id', 'type', 'weekday']);
        });

        // Giờ mở cửa cũ thành ca mở cửa, để chỉ còn một nơi lưu giờ mở cửa.
        foreach (DB::table('restaurants')->whereNotNull('opening_hours')->get() as $restaurant) {
            foreach (json_decode($restaurant->opening_hours, true) as $day => $hours) {
                if ($hours !== null) {
                    DB::table('shifts')->insert([
                        'restaurant_id' => $restaurant->id,
                        'type' => 'opening',
                        'weekday' => $day,
                        'name' => 'Cả ngày',
                        'start_time' => $hours['opens'],
                        'end_time' => $hours['closes'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('opening_hours')->nullable()->after('theme');
        });

        foreach (DB::table('shifts')->where('type', 'opening')->get()->groupBy('restaurant_id') as $restaurantId => $shifts) {
            $hours = [];

            foreach (range(1, 7) as $day) {
                $today = $shifts->where('weekday', $day);

                $hours[$day] = $today->isEmpty() ? null : ['opens' => $today->min('start_time'), 'closes' => $today->max('end_time')];
            }

            DB::table('restaurants')->where('id', $restaurantId)->update(['opening_hours' => json_encode($hours)]);
        }

        Schema::dropIfExists('shifts');
    }
};
