<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('takeaway_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 20);
            $table->dateTime('pickup_at');
            $table->unsignedInteger('total');
            $table->text('notes')->nullable();
            $table->string('locale', 5)->default('vi');
            $table->timestamps();

            $table->unique(['restaurant_id', 'number']);
            $table->index(['restaurant_id', 'pickup_at']);
        });

        // Tên và giá chụp lại lúc đặt: sửa menu sau đó không làm đổi đơn cũ.
        Schema::create('takeaway_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('takeaway_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('takeaway_order_items');
        Schema::dropIfExists('takeaway_orders');
    }
};
