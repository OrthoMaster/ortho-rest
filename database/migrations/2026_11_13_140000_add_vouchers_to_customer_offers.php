<?php

use App\Models\CustomerOffer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ưu đãi lần sau thành voucher có mã: khách không để lại thông tin vẫn nhận được
     * (lưu trên điện thoại qua mã QR), dùng được tại bàn và cho đơn mang đi.
     */
    public function up(): void
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
            $table->string('code', 12)->nullable()->after('customer_id');
            $table->string('token', 40)->nullable()->after('code');
            $table->foreignId('used_takeaway_order_id')->nullable()->after('used_table_id')->constrained('takeaway_orders')->nullOnDelete();
        });

        DB::table('customer_offers')->orderBy('id')->each(function (object $offer): void {
            DB::table('customer_offers')->where('id', $offer->id)->update(['code' => CustomerOffer::newCode($offer->restaurant_id), 'token' => CustomerOffer::newToken()]);
        });

        Schema::table('customer_offers', function (Blueprint $table) {
            $table->unique(['restaurant_id', 'code']);
            $table->unique('token');
        });

        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->foreignId('customer_offer_id')->nullable()->after('discount_code')->constrained('customer_offers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_offer_id');
        });

        Schema::table('customer_offers', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'code']);
            $table->dropUnique(['token']);
            $table->dropConstrainedForeignId('used_takeaway_order_id');
            $table->dropColumn(['code', 'token']);
        });
    }
};
