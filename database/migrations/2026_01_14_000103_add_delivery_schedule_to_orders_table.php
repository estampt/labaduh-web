<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('pickup_time_end');
            }
            if (!Schema::hasColumn('orders', 'delivery_time_start')) {
                $table->time('delivery_time_start')->nullable()->after('delivery_date');
            }
            if (!Schema::hasColumn('orders', 'delivery_time_end')) {
                $table->time('delivery_time_end')->nullable()->after('delivery_time_start');
            }
            if (!Schema::hasColumn('orders', 'job_request_id')) {
                $table->unsignedBigInteger('job_request_id')->nullable()->index()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_time_end')) $table->dropColumn('delivery_time_end');
            if (Schema::hasColumn('orders', 'delivery_time_start')) $table->dropColumn('delivery_time_start');
            if (Schema::hasColumn('orders', 'delivery_date')) $table->dropColumn('delivery_date');
            if (Schema::hasColumn('orders', 'job_request_id')) $table->dropColumn('job_request_id');
        });
    }
};
