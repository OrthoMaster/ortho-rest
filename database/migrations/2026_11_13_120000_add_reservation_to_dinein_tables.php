<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Khách đặt trước đến thì xếp bàn luôn: lượt bàn nhớ đặt bàn nào để nối thông tin khách. */
    public function up(): void
    {
        Schema::table('dinein_tables', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dinein_tables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
