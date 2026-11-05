<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('image_path')->nullable();
            // Có quán chỉ muốn hiện hình, không hiện chữ.
            $table->boolean('image_only')->default(false);
            $table->timestamps();
        });

        Schema::create('menu_item_tag', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['menu_item_id', 'menu_tag_id']);
        });

        // Quán đã bật Menu trước khi có nhãn: tạo sẵn như lúc bật module.
        $now = now();

        foreach (DB::table('restaurant_modules')->where('module', 'menu')->pluck('restaurant_id') as $restaurantId) {
            foreach (['Best-seller', 'Must-try', 'Món chay'] as $name) {
                DB::table('menu_tags')->insert([
                    'restaurant_id' => $restaurantId,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_tag');
        Schema::dropIfExists('menu_tags');
    }
};
