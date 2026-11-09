<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lấy đơn tại bàn: khu phục vụ (Bếp, Bar...) và món của từng khu, phân công
 * người theo ngày và ca, bàn đang mở và các món đã gọi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinein_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('dinein_station_id')->nullable()->after('menu_category_id')->constrained('dinein_stations')->nullOnDelete();
        });

        // Một dòng là một người ở một khu (bếp, bar) hoặc khu vực bàn trong một ca của một ngày.
        // shift_id null: cả ngày, khi quán chưa khai ca làm.
        Schema::create('dinein_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('dinein_station_id')->nullable()->constrained('dinein_stations')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->index(['restaurant_id', 'work_date']);
        });

        // Một lượt khách ngồi ở một bàn, từ lúc gọi món đầu tiên đến khi đóng bàn.
        Schema::create('dinein_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('table_number');
            $table->string('label', 8);
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'area_id', 'table_number', 'closed_at']);
        });

        // Món đã gọi. Tên, giá, tuỳ chọn chép lại lúc gọi để sửa menu sau không đổi bàn đang ngồi.
        Schema::create('dinein_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dinein_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dinein_station_id')->nullable()->constrained('dinein_stations')->nullOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->string('name');
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->json('options')->nullable();
            $table->string('note', 200)->nullable();
            $table->string('status', 12)->default('new');
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinein_lines');
        Schema::dropIfExists('dinein_tables');
        Schema::dropIfExists('dinein_assignments');
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dinein_station_id');
        });
        Schema::dropIfExists('dinein_stations');
    }
};
