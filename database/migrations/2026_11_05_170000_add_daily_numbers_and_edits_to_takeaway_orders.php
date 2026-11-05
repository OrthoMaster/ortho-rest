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
            $table->date('order_date')->nullable()->after('number');
        });

        DB::table('takeaway_orders')->update(['order_date' => DB::raw('DATE(created_at)')]);

        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->date('order_date')->nullable(false)->change();
            $table->unique(['restaurant_id', 'order_date', 'number']);
            $table->dropUnique(['restaurant_id', 'number']);
        });

        $this->renumber(['restaurant_id', 'order_date']);

        Schema::create('takeaway_order_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('takeaway_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('changes');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('takeaway_order_edits');

        $this->renumber(['restaurant_id']);

        Schema::table('takeaway_orders', function (Blueprint $table) {
            $table->unique(['restaurant_id', 'number']);
            $table->dropUnique(['restaurant_id', 'order_date', 'number']);
            $table->dropColumn('order_date');
        });
    }

    /** @param list<string> $groupBy */
    private function renumber(array $groupBy): void
    {
        // Đẩy số ra xa trước để lúc đánh lại không đụng khoá unique.
        DB::table('takeaway_orders')->update(['number' => DB::raw('number + '.self::OFFSET)]);

        $counters = [];

        foreach (DB::table('takeaway_orders')->orderBy('id')->get() as $order) {
            $key = implode('|', array_map(fn (string $column) => $order->{$column}, $groupBy));
            $counters[$key] = ($counters[$key] ?? 0) + 1;

            DB::table('takeaway_orders')->where('id', $order->id)->update(['number' => $counters[$key]]);
        }
    }
};
