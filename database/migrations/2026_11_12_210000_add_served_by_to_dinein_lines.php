<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ghi ai bấm Đã mang ra (cùng với ai gọi, ai báo xong, ai huỷ đã có). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dinein_lines', function (Blueprint $table) {
            $table->foreignId('served_by')->nullable()->after('served_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dinein_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('served_by');
        });
    }
};
