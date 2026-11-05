<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('type', 20);
            $table->unsignedInteger('value');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'code']);
        });

        // Đơn lưu lại cách giảm lúc đặt: sửa hay xoá mã sau đó không làm đổi đơn cũ.
        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->unsignedInteger('subtotal')->default(0)->after('pickup_at');
            $table->string('discount_type', 20)->nullable()->after('subtotal');
            $table->unsignedInteger('discount_value')->nullable()->after('discount_type');
            $table->string('discount_code', 40)->nullable()->after('discount_value');
            $table->unsignedInteger('discount_amount')->default(0)->after('discount_code');
        });

        DB::table('takeaway_orders')->update(['subtotal' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'discount_type', 'discount_value', 'discount_code', 'discount_amount']);
        });

        Schema::dropIfExists('discount_codes');
    }
};
