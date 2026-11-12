<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Điều chỉnh phép còn lại bằng tay (vào giữa tháng, tạm nghỉ một tháng...):
 * lưu số ngày cộng / trừ, phép còn lại trước và sau, lý do, ai chỉnh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('days', 6, 2);
            $table->decimal('before', 7, 2);
            $table->decimal('after', 7, 2);
            $table->string('reason', 300);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['restaurant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_adjustments');
    }
};
