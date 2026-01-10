<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_shops', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_shops', 'default_max_orders_per_day')) {
                $table->unsignedInteger('default_max_orders_per_day')->default(50)->after('service_radius_km');
            }
            if (!Schema::hasColumn('vendor_shops', 'default_max_kg_per_day')) {
                $table->decimal('default_max_kg_per_day', 10, 2)->default(300)->after('default_max_orders_per_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_shops', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_shops', 'default_max_kg_per_day')) $table->dropColumn('default_max_kg_per_day');
            if (Schema::hasColumn('vendor_shops', 'default_max_orders_per_day')) $table->dropColumn('default_max_orders_per_day');
        });
    }
};
