<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tuỳ chọn khi gọi món, riêng từng món: nhóm (Thịt, Nước chấm...) chọn một
 * hoặc nhiều, bắt buộc hay không; mỗi lựa chọn có thể cộng thêm tiền.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_multiple')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_options');
        Schema::dropIfExists('menu_item_option_groups');
    }
};
