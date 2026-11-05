<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OFFSET = 100000000;

    public function up(): void
    {
        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->char('code_prefix', 1)->default('M')->after('number');
            $table->date('pickup_date')->nullable()->after('code_prefix');
        });

        DB::table('takeaway_orders')->update([
            'pickup_date' => DB::raw('DATE(pickup_at)'),
            'number' => DB::raw('id + '.self::OFFSET),
        ]);

        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->date('pickup_date')->nullable(false)->change();
            $table->unique(['restaurant_id', 'pickup_date', 'number']);
            $table->dropUnique(['restaurant_id', 'order_date', 'number']);
            $table->dropColumn('order_date');
        });

        $this->renumber('pickup_date');
    }

    public function down(): void
    {
        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->date('order_date')->nullable()->after('number');
        });

        DB::table('takeaway_orders')->update([
            'order_date' => DB::raw('DATE(created_at)'),
            'number' => DB::raw('id + '.self::OFFSET),
        ]);

        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->date('order_date')->nullable(false)->change();
            $table->unique(['restaurant_id', 'order_date', 'number']);
            $table->dropUnique(['restaurant_id', 'pickup_date', 'number']);
            $table->dropColumn(['code_prefix', 'pickup_date']);
        });

        $this->renumber('order_date');
    }

    private function renumber(string $dateColumn): void
    {
        $counters = [];

        foreach (DB::table('takeaway_orders')->orderBy('id')->get() as $order) {
            $key = $order->restaurant_id.'|'.$order->{$dateColumn};
            $counters[$key] = ($counters[$key] ?? 0) + 1;

            DB::table('takeaway_orders')->where('id', $order->id)->update(['number' => $counters[$key]]);
        }
    }
};
