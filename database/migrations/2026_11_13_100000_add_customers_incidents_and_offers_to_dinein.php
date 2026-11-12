<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bàn tại quán:
 * - gắn khách theo số điện thoại (không đưa khách giữ gì, lần sau tra theo số điện thoại);
 * - giảm giá trên phiếu tạm tính (từ ưu đãi của khách);
 * - sự cố khi phục vụ (loại, nội dung, ai ghi, lúc nào);
 * - ưu đãi lần sau của khách: giảm % hoặc số tiền, lý do, hạn dùng, dùng một lần.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->unsignedInteger('value');
            $table->string('reason', 300);
            $table->date('expires_on')->nullable();
            $table->foreignId('dinein_table_id')->nullable()->constrained('dinein_tables')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_table_id')->nullable()->constrained('dinein_tables')->nullOnDelete();
            $table->timestamps();
            $table->index(['restaurant_id', 'customer_id']);
        });

        Schema::table('dinein_tables', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('label')->constrained()->nullOnDelete();
            $table->string('discount_type', 10)->nullable()->after('customer_id');
            $table->unsignedInteger('discount_value')->nullable()->after('discount_type');
            $table->unsignedInteger('discount_amount')->default(0)->after('discount_value');
            $table->foreignId('customer_offer_id')->nullable()->after('discount_amount')->constrained('customer_offers')->nullOnDelete();
        });

        Schema::create('dinein_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dinein_table_id')->constrained('dinein_tables')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->text('note');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['restaurant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinein_incidents');

        Schema::table('dinein_tables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_offer_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']);
        });

        Schema::dropIfExists('customer_offers');
    }
};
