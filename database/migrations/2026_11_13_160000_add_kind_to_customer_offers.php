<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ưu đãi có hai loại: khách quen, và bù cho khách gặp sự cố (tặng ngay lúc ghi sự cố).
     * Lý do của ưu đãi và nội dung sự cố không bắt buộc nữa.
     */
    public function up(): void
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->string('kind', 20)->default('loyal')->after('customer_id');
            $table->foreignId('incident_id')->nullable()->after('dinein_table_id')->constrained('dinein_incidents')->nullOnDelete();
            $table->string('reason', 300)->nullable()->change();
        });

        Schema::table('dinein_incidents', function (Blueprint $table) {
            $table->text('note')->nullable()->change();
        });

        // Ưu đãi đã tặng ở bàn có ghi sự cố coi như bù sự cố.
        DB::table('customer_offers')->whereIn('dinein_table_id', DB::table('dinein_incidents')->select('dinein_table_id'))->update(['kind' => 'incident']);
    }

    public function down(): void
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('incident_id');
            $table->dropColumn('kind');
        });
    }
};
