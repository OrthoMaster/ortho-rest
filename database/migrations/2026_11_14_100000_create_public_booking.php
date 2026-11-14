<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trang đặt bàn công khai và email của quán:
 * - liên hệ của quán (hiện trên trang đặt bàn, trong email, trả lời email về đây);
 * - mã riêng của mỗi đặt bàn để khách xem, tự huỷ qua link;
 * - trang đặt bàn tự tạo (bản nháp, đã đăng, bản cũ);
 * - máy chủ email riêng và mẫu email của quán.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('timezone');
            $table->string('email')->nullable()->after('phone');
            $table->string('address')->nullable()->after('email');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('public_token', 32)->nullable()->unique()->after('locale');
        });

        Schema::create('booking_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // draft: đang sửa, published: đang chạy, archived: bản cũ khôi phục được.
            $table->string('status', 20)->index();
            $table->string('file_name')->nullable();
            $table->longText('html');
            $table->json('checks');
            $table->timestamp('tested_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('use_smtp')->default(false);
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 10)->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->json('notify_emails')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
            $table->unique(['restaurant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('mail_settings');
        Schema::dropIfExists('booking_pages');
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('public_token'));
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn(['phone', 'email', 'address']));
    }
};
