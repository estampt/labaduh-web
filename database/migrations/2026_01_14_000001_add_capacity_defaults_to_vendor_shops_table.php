<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_shops', function (Blueprint $table) {

            // ✅ Capacity defaults (place after longitude since it exists)
            if (!Schema::hasColumn('vendor_shops', 'default_max_orders_per_day')) {
                $table->unsignedInteger('default_max_orders_per_day')
                    ->default(50)
                    ->after('longitude');
            }

            if (!Schema::hasColumn('vendor_shops', 'default_max_kg_per_day')) {
                $table->decimal('default_max_kg_per_day', 10, 2)
                    ->default(300)
                    ->after('default_max_orders_per_day');
            }

            // ✅ Structured address (FIX: check vendor_shops, not users)
            if (!Schema::hasColumn('vendor_shops', 'address_line1')) {
                $table->string('address_line1')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('vendor_shops', 'address_line2')) {
                $table->string('address_line2')->nullable()->after('address_line1');
            }

            if (!Schema::hasColumn('vendor_shops', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('address_line2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_shops', function (Blueprint $table) {
            foreach ([
                'postal_code',
                'address_line2',
                'address_line1',
                'default_max_kg_per_day',
                'default_max_orders_per_day',
            ] as $col) {
                if (Schema::hasColumn('vendor_shops', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
