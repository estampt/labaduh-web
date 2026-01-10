<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'completed_orders_count')) {
                $table->unsignedBigInteger('completed_orders_count')->default(0)->after('customers_serviced_count');
            }
            if (!Schema::hasColumn('vendors', 'unique_customers_served_count')) {
                $table->unsignedBigInteger('unique_customers_served_count')->default(0)->after('completed_orders_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'unique_customers_served_count')) {
                $table->dropColumn('unique_customers_served_count');
            }
            if (Schema::hasColumn('vendors', 'completed_orders_count')) {
                $table->dropColumn('completed_orders_count');
            }
        });
    }
};
