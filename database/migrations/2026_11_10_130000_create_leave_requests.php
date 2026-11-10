<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Đơn xin nghỉ theo ngày hoặc theo ca; người duyệt chọn có lương (trừ phép) hay không. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            // Chỉ khi nghỉ một ngày: các ca nghỉ. Null là nghỉ cả ngày.
            $table->json('shift_ids')->nullable();
            $table->decimal('days', 5, 2);
            $table->string('reason', 500)->nullable();
            $table->string('status', 10)->default('pending');
            $table->boolean('is_paid')->nullable();
            $table->string('decision_note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
            $table->index(['restaurant_id', 'user_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
