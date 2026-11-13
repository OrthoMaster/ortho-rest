<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Giờ đơn mang đi làm xong và giao khách, để thống kê đơn trễ so với giờ hẹn. */
    public function up(): void
    {
        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->dropColumn(['ready_at', 'delivered_at']);
        });
    }
};
