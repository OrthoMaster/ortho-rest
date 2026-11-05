<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('opening_hours')->nullable()->after('theme');
            $table->json('closed_dates')->nullable()->after('opening_hours');
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->boolean('online_bookable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'phone']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reserved_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('party_size');
            $table->string('status', 20);
            $table->string('source', 20);
            $table->text('notes')->nullable();
            $table->string('locale', 5)->default('vi');
            $table->timestamps();

            $table->index(['restaurant_id', 'reserved_at']);
            $table->index(['area_id', 'reserved_at']);
        });

        Schema::create('reservation_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_status_logs');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('areas');

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['opening_hours', 'closed_dates']);
        });
    }
};
