<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Máy (trình duyệt, app đã cài) đã bật thông báo đẩy của một user. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            // Địa chỉ đẩy dài, không đánh unique trực tiếp được: so bằng mã băm.
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key', 120);
            $table->string('auth_token', 60);
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
