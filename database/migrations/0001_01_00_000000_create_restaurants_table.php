<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 60)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->timestamps();
        });

        Schema::create('restaurant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 190)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('restaurant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50);
            $table->boolean('enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_modules');
        Schema::dropIfExists('restaurant_domains');
        Schema::dropIfExists('restaurants');
    }
};
