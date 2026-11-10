<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bộ phận (Bếp, Phục vụ...) theo hợp đồng của nhân viên, thông tin hợp đồng,
 * và việc trong ca (Đứng thanh toán, Tiếp thực...) để xếp lịch theo từng việc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedTinyInteger('tone')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->date('started_on')->nullable()->after('department_id');
            $table->decimal('paid_leave_per_month', 4, 2)->nullable()->after('started_on');
            $table->decimal('contract_hours', 5, 1)->nullable()->after('paid_leave_per_month');
            $table->string('contract_period', 5)->nullable()->after('contract_hours');
        });

        foreach (['dinein_stations', 'areas'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::create('schedule_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            // Không có khu: việc chung cho cả quán (Kiểm kho...).
            $table->foreignId('dinein_station_id')->nullable()->constrained('dinein_stations')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->foreignId('schedule_task_id')->nullable()->after('area_id')->constrained()->cascadeOnDelete();
        });

        // Quán đã bật Nội bộ thì có sẵn hai bộ phận.
        $restaurants = DB::table('restaurant_modules')->where('module', 'staff')->where('enabled', true)->pluck('restaurant_id');
        foreach ($restaurants as $restaurantId) {
            foreach ([['Phục vụ', 1], ['Bếp', 2]] as $order => [$name, $tone]) {
                DB::table('departments')->insertOrIgnore(['restaurant_id' => $restaurantId, 'name' => $name, 'tone' => $tone, 'sort_order' => $order + 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('schedule_assignments', fn (Blueprint $table) => $table->dropConstrainedForeignId('schedule_task_id'));
        Schema::dropIfExists('schedule_tasks');
        foreach (['dinein_stations', 'areas'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('department_id'));
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['started_on', 'paid_leave_per_month', 'contract_hours', 'contract_period']);
        });
        Schema::dropIfExists('departments');
    }
};
