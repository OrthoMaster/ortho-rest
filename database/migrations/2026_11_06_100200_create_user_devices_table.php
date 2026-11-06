<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('devices_locked')->default(false)->after('locale');
        });

        // Thiết bị thuộc về user, không có restaurant_id (giống bảng users).
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64);
            $table->string('name');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('devices_locked');
        });
    }
};
